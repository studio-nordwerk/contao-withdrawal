<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Nordwerk\WithdrawalBundle\Command\ResendPendingCommand;
use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class MailConcurrencyTest extends TestCase
{
    private Connection $connection;
    private WithdrawalRepository $repository;
    private Environment $twig;
    private int $id;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'host' => 'db', 'user' => 'contao', 'password' => 'contao', 'dbname' => 'contao']);
        $this->repository = new WithdrawalRepository($this->connection);
        $this->id = $this->repository->create(bin2hex(random_bytes(32)), new WithdrawalDeclaration('Ada', 'MAIL-AUDIT', 'ada@example.test'), new \DateTimeImmutable());
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__.'/../templates', 'NordwerkWithdrawal');
        $this->twig = new Environment($loader);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('tl_withdrawal', ['id' => $this->id]);
        $this->connection->close();
    }

    public function testUnresponsiveSmtpHonorsConfiguredTimeoutAndLeavesRecordPending(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($server);
        $address = stream_socket_get_name($server, false);
        $this->assertIsString($address);
        $port = parse_url('tcp://'.$address, PHP_URL_PORT);
        $this->assertIsInt($port);
        $transport = new EsmtpTransport('127.0.0.1', $port, false);
        $stream = $transport->getStream();
        $this->assertInstanceOf(SocketStream::class, $stream);
        $stream->setTimeout(0.1);
        $row = $this->repository->find($this->id);
        $this->assertNotNull($row);
        $start = microtime(true);

        try {
            (new WithdrawalMailer($transport, $this->twig, $this->repository, 'shop@example.test'))->sendPending($row);
            $this->fail('A server without an SMTP greeting must time out.');
        } catch (TransportExceptionInterface) {
            $this->assertLessThan(3.0, microtime(true) - $start);
            $stored = $this->repository->find($this->id);
            $this->assertNotNull($stored);
            $this->assertSame('pending', $stored['mailStatus']);
            $this->assertNull($stored['confirmationSentAt']);
        } finally {
            fclose($server);
        }
    }

    public function testSynchronousTransportRejectionCannotBeMarkedSent(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturn(null)
        ;
        $row = $this->repository->find($this->id);
        $this->assertNotNull($row);

        try {
            (new WithdrawalMailer($transport, $this->twig, $this->repository, 'shop@example.test'))->sendPending($row);
            $this->fail('A rejected message must remain pending.');
        } catch (\RuntimeException) {
            $stored = $this->repository->find($this->id);
            $this->assertNotNull($stored);
            $this->assertSame('pending', $stored['mailStatus']);
            $this->assertNull($stored['confirmationSentAt']);
        }
    }

    public function testRetryCommandDoesNotPrintTransportPersonalData(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport
            ->method('send')
            ->willThrowException(new \RuntimeException('Rejected ada@example.test; AUTH secret; NAME Ada'))
        ;
        $command = new ResendPendingCommand($this->repository, new WithdrawalMailer($transport, $this->twig, $this->repository, 'shop@example.test'));
        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));
        $this->assertStringNotContainsString('ada@example.test', $tester->getDisplay());
        $this->assertStringNotContainsString('secret', $tester->getDisplay());
        $this->assertStringContainsString((string) $this->id, $tester->getDisplay());
    }

    public function testRejectedConsumerAddressDoesNotBlockMerchantAndRetrySkipsMerchant(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $attempts = [];
        $transport
            ->method('send')
            ->willReturnCallback(
                static function (Email $email) use (&$attempts): SentMessage {
                    $recipient = $email->getTo()[0]->getAddress();
                    $attempts[] = $recipient;
                    if ('ada@example.test' === $recipient) {
                        throw new \RuntimeException('Mailbox rejected');
                    }

                    return new SentMessage($email, Envelope::create($email));
                },
            )
        ;
        $mailer = new WithdrawalMailer($transport, $this->twig, $this->repository, 'shop@example.test');

        for ($i = 0; $i < 2; ++$i) {
            $row = $this->repository->find($this->id);
            $this->assertNotNull($row);

            try {
                $mailer->sendPending($row);
                $this->fail('Expected failed consumer delivery.');
            } catch (\RuntimeException) {
                $stored = $this->repository->find($this->id);
                $this->assertNotNull($stored);
                $this->assertSame('pending', $stored['mailStatus']);
            }
        }
        $this->assertSame(['ada@example.test', 'shop@example.test', 'ada@example.test'], $attempts);
        $stored = $this->repository->find($this->id);
        $this->assertNotNull($stored);
        $this->assertNotNull($stored['merchantSentAt']);
    }

    public function testStalePendingSnapshotDoesNotSendAgain(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(static fn (Email $email): SentMessage => new SentMessage($email, Envelope::create($email)))
        ;
        $mailer = new WithdrawalMailer($transport, $this->twig, $this->repository, 'shop@example.test');
        $row = $this->repository->find($this->id);
        $this->assertNotNull($row);
        $mailer->sendPending($row);
        $mailer->sendPending($row);
    }

    public function testOverlappingWorkersDoNotSendTheSameReceipt(): void
    {
        $otherConnection = DriverManager::getConnection($this->connection->getParams());
        $otherRepository = new WithdrawalRepository($otherConnection);
        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport
            ->expects($this->never())
            ->method('send')
        ;
        $otherMailer = new WithdrawalMailer($otherTransport, $this->twig, $otherRepository, 'shop@example.test');
        $transport = $this->createMock(TransportInterface::class);
        $transport
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(
                function (Email $email) use ($otherMailer): SentMessage {
                    $row = $this->repository->find($this->id);
                    $this->assertNotNull($row);
                    $otherMailer->sendPending($row);

                    return new SentMessage($email, Envelope::create($email));
                },
            )
        ;
        $row = $this->repository->find($this->id);
        $this->assertNotNull($row);

        try {
            (new WithdrawalMailer($transport, $this->twig, $this->repository, 'shop@example.test'))->sendPending($row);
        } finally {
            $otherConnection->close();
        }
    }
}
