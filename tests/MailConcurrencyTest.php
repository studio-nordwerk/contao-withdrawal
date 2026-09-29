<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
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

    public function testRejectedConsumerAddressDoesNotBlockMerchantAndRetrySkipsMerchant(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $attempts = [];
        $transport
            ->method('send')
            ->willReturnCallback(
                static function (Email $email) use (&$attempts): void {
                    $recipient = $email->getTo()[0]->getAddress();
                    $attempts[] = $recipient;
                    if ('ada@example.test' === $recipient) {
                        throw new \RuntimeException('Mailbox rejected');
                    }
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
        $transport = $this->createMock(MailerInterface::class);
        $transport
            ->expects($this->exactly(2))
            ->method('send')
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
        $otherTransport = $this->createMock(MailerInterface::class);
        $otherTransport
            ->expects($this->never())
            ->method('send')
        ;
        $otherMailer = new WithdrawalMailer($otherTransport, $this->twig, $otherRepository, 'shop@example.test');
        $transport = $this->createMock(MailerInterface::class);
        $transport
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(
                function () use ($otherMailer): void {
                    $row = $this->repository->find($this->id);
                    $this->assertNotNull($row);
                    $otherMailer->sendPending($row);
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
