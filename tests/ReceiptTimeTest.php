<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Doctrine\DBAL\Connection;
use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;
use Nordwerk\WithdrawalBundle\Http\ReceiptTime;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ReceiptTimeTest extends TestCase
{
    public function testRepositoryConvertsTimezonesBeforeAppendingUtcSuffix(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'tl_withdrawal',
                $this->callback(static fn (array $row): bool => '2026-06-19T21:59:59.123456Z' === $row['submittedAt']),
            )
        ;

        $connection
            ->method('lastInsertId')
            ->willReturn('1')
        ;
        (new WithdrawalRepository($connection))->create(str_repeat('a', 64), new WithdrawalDeclaration('Ada', 'Last day', 'a@example.test'), new \DateTimeImmutable('2026-06-19T23:59:59.123456+02:00'));
    }

    public function testReceiptUsesHttpArrivalBeforeMidnightNotLaterControllerExecution(): void
    {
        $request = new Request(server: ['REQUEST_TIME_FLOAT' => (float) (new \DateTimeImmutable('2026-06-19T23:59:59.123456+02:00'))->format('U.u')]);
        $request->request->set('submittedAt', '1900-01-01');
        $this->assertSame('2026-06-19T21:59:59.123456+00:00', ReceiptTime::fromRequest($request)->format('Y-m-d\TH:i:s.uP'));
    }

    public function testMailDisambiguatesSummerWinterAndRepeatedAutumnHour(): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__.'/../templates/mail'));

        foreach ([
            ['2026-06-19T21:59:59Z', '23:59:59', '+02:00'],
            ['2026-12-19T22:59:59Z', '23:59:59', '+01:00'],
            ['2026-03-29T00:59:59Z', '01:59:59', '+01:00'],
            ['2026-03-29T01:00:00Z', '03:00:00', '+02:00'],
            ['2026-10-25T00:30:00Z', '02:30:00', '+02:00'],
            ['2026-10-25T01:30:00Z', '02:30:00', '+01:00'],
        ] as [$utc, $time, $offset]) {
            foreach (['consumer', 'merchant', 'consumer.en', 'merchant.en'] as $template) {
                $body = $twig->render($template.'.txt.twig', ['name' => 'Ada', 'contractReference' => 'Deadline', 'email' => 'a@example.test', 'submittedAt' => new \DateTimeImmutable($utc)]);
                $this->assertStringContainsString($time, $body);
                $this->assertStringContainsString($offset, $body);
            }
        }
    }
}
