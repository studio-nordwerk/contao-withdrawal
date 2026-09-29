<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Settings;

use Doctrine\DBAL\Connection;
use Nordwerk\WithdrawalBundle\Mail\MailOptions;

final readonly class WithdrawalSettings
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{merchantEmail: string, path: string, baseStylesEnabled: bool}
     */
    public function stored(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM tl_withdrawal_settings WHERE id = 1');

        return false === $row ? ['merchantEmail' => '', 'path' => '/withdrawal', 'baseStylesEnabled' => true] : [
            'merchantEmail' => (string) $row['merchantEmail'],
            'path' => (string) $row['path'],
            // Until the database migration has added the column, the base styling stays on.
            'baseStylesEnabled' => !\array_key_exists('baseStylesEnabled', $row) || '1' === (string) $row['baseStylesEnabled'],
        ];
    }

    public function baseStylesEnabled(): bool
    {
        return $this->stored()['baseStylesEnabled'];
    }

    /**
     * @return array<string, mixed>
     */
    public function mailOptions(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM tl_withdrawal_settings WHERE id = 1');
        $raw = false === $row ? null : ($row['mailOptions'] ?? null);
        $values = \is_string($raw) && '' !== $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];

        return MailOptions::validate(\is_array($values) ? $values : [], 'withdrawal');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function saveMailOptions(array $options): void
    {
        $options = MailOptions::validate($options, 'withdrawal');
        $data = ['tstamp' => time(), 'mailOptions' => json_encode($options, JSON_THROW_ON_ERROR)];
        if ($this->connection->fetchOne('SELECT id FROM tl_withdrawal_settings WHERE id = 1')) {
            $this->connection->update('tl_withdrawal_settings', $data, ['id' => 1]);
        } else {
            $this->connection->insert('tl_withdrawal_settings', ['id' => 1, 'merchantEmail' => '', 'path' => '/withdrawal', ...$data]);
        }
    }

    public function merchantEmail(): string
    {
        $email = $this->override('WITHDRAWAL_MERCHANT_EMAIL') ?: $this->stored()['merchantEmail'];
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
            throw new \InvalidArgumentException('A valid withdrawal merchant email is required.');
        }

        return $email;
    }

    public function path(): string
    {
        $path = $this->override('WITHDRAWAL_PATH') ?: $this->stored()['path'];
        self::assertPath($path);

        return $path;
    }

    public function save(string $email, string $path, bool $baseStylesEnabled = true): void
    {
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
            throw new \InvalidArgumentException('Bitte eine gültige Händleradresse eingeben.');
        }
        self::assertPath($path);
        $data = ['tstamp' => time(), 'merchantEmail' => $email, 'path' => $path];
        if ($this->hasStylesColumn()) {
            $data['baseStylesEnabled'] = $baseStylesEnabled ? '1' : '';
        }
        if ($this->connection->fetchOne('SELECT id FROM tl_withdrawal_settings WHERE id = 1')) {
            $this->connection->update('tl_withdrawal_settings', $data, ['id' => 1]);
        } else {
            $this->connection->insert('tl_withdrawal_settings', ['id' => 1, ...$data]);
        }
    }

    private function hasStylesColumn(): bool
    {
        return $this->connection->createSchemaManager()->introspectTable('tl_withdrawal_settings')->hasColumn('baseStylesEnabled');
    }

    private static function assertPath(string $path): void
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n?#]/', $path)) {
            throw new \InvalidArgumentException('Der Widerrufspfad muss mit einem einzelnen / beginnen und darf keine Query oder Fragment enthalten.');
        }
    }

    private function override(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) ? trim($value) : '';
    }
}
