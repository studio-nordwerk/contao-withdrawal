<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Persistence;

use Doctrine\DBAL\Connection;
use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;

final readonly class WithdrawalRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(string $token, WithdrawalDeclaration $declaration, \DateTimeImmutable $submittedAt, string $locale = 'de'): int
    {
        $this->connection->insert('tl_withdrawal', [
            'tstamp' => $submittedAt->getTimestamp(),
            'submissionToken' => $token,
            'consumerName' => $declaration->name,
            'contractReference' => $declaration->contractReference,
            'email' => $declaration->email,
            'locale' => str_starts_with($locale, 'en') ? 'en' : 'de',
            'submittedAt' => $submittedAt->format('Y-m-d\\TH:i:s.u\\Z'),
            'status' => 'new',
            'mailStatus' => 'pending',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): array|null
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM tl_withdrawal WHERE submissionToken = ?', [$token]);

        return false === $row ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): array|null
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM tl_withdrawal WHERE id = ?', [$id]);

        return false === $row ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->connection->fetchAllAssociative("SELECT * FROM tl_withdrawal WHERE mailStatus <> 'sent' ORDER BY id ASC");
    }

    /**
     * @param callable(array<string, mixed>): void $send
     */
    public function withMailLock(int $id, callable $send): void
    {
        // Connection-scoped MySQL/MariaDB locks are shared across web/cron hosts and
        // released by the database if a worker dies. Never wait in the form.
        $key = 'withdrawal:'.hash('sha256', $this->connection->getDatabase().':'.$id);
        $key = substr($key, 0, 64);
        if (1 !== (int) $this->connection->fetchOne('SELECT GET_LOCK(?, 0)', [$key])) {
            return;
        }

        try {
            $row = $this->find($id);
            if (null !== $row) {
                $send($row);
            }
        } finally {
            $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$key]);
        }
    }

    public function markSent(int $id, string $column, \DateTimeImmutable $at): void
    {
        if (!\in_array($column, ['confirmationSentAt', 'merchantSentAt'], true)) {
            throw new \InvalidArgumentException('Invalid mail column.');
        }

        $this->connection->update('tl_withdrawal', [$column => $at->format('Y-m-d\\TH:i:s.u\\Z')], ['id' => $id]);
        $this->connection->executeStatement("UPDATE tl_withdrawal SET mailStatus = CASE WHEN confirmationSentAt IS NOT NULL AND merchantSentAt IS NOT NULL THEN 'sent' ELSE 'pending' END WHERE id = ?", [$id]);
    }
}
