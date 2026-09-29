<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Nordwerk\WithdrawalBundle\Command\ResendPendingCommand;
use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class MailRetryTest extends TestCase
{
    public function testFailedDeliveryLeavesStoredRecordForRetry(): void
    {
        $url = (string) (getenv('DATABASE_URL') ?: 'mysql://contao:contao@db:3306/contao');
        $parts = parse_url($url);
        $this->assertIsArray($parts);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $parts['host'] ?? 'db',
            'port' => $parts['port'] ?? 3306,
            'user' => $parts['user'] ?? 'contao',
            'password' => $parts['pass'] ?? 'contao',
            'dbname' => ltrim($parts['path'] ?? '/contao', '/'),
        ]);
        $repository = new WithdrawalRepository($connection);
        $token = bin2hex(random_bytes(32));
        $id = $repository->create($token, new WithdrawalDeclaration('Ada Example', 'ORDER-123', 'ada@example.test'), new \DateTimeImmutable('2026-09-29 12:34:56.123456', new \DateTimeZone('UTC')));
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__.'/../templates', 'NordwerkWithdrawal');
        $twig = new Environment($loader);

        $failing = new class() implements MailerInterface {
            public function send(RawMessage $message, Envelope|null $envelope = null): void
            {
                throw new \RuntimeException('SMTP unavailable');
            }
        };

        $row = $repository->find($id);
        $this->assertNotNull($row);
        $this->assertSame('2026-09-29T12:34:56.123456Z', $row['submittedAt']);

        try {
            (new WithdrawalMailer($failing, $twig, $repository, 'shop@example.test'))->sendPending($row);
            $this->fail('Expected mail delivery failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $row = $repository->find($id);
        $this->assertNotNull($row);
        $this->assertSame('pending', $row['mailStatus']);
        $this->assertNull($row['confirmationSentAt']);

        $working = new class() implements MailerInterface {
            /**
             * @var list<RawMessage>
             */
            public array $sent = [];

            public function send(RawMessage $message, Envelope|null $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };

        $command = new ResendPendingCommand($repository, new WithdrawalMailer($working, $twig, $repository, 'shop@example.test'));
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute([]));
        $this->assertCount(2, $working->sent);
        $row = $repository->find($id);
        $this->assertNotNull($row);
        $this->assertSame('sent', $row['mailStatus']);
        $connection->delete('tl_withdrawal', ['id' => $id]);
    }
}
