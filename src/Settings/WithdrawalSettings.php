<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Settings;

use Doctrine\DBAL\Connection;

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
        $row = $this->connection->fetchAssociative('SELECT merchantEmail, path, baseStylesEnabled FROM tl_withdrawal_settings WHERE id = 1');

        return false === $row ? ['merchantEmail' => '', 'path' => '/withdrawal', 'baseStylesEnabled' => true] : [
            'merchantEmail' => (string) $row['merchantEmail'],
            'path' => (string) $row['path'],
            'baseStylesEnabled' => '1' === (string) $row['baseStylesEnabled'],
        ];
    }

    public function baseStylesEnabled(): bool
    {
        return $this->stored()['baseStylesEnabled'];
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
        $data = ['tstamp' => time(), 'merchantEmail' => $email, 'path' => $path, 'baseStylesEnabled' => $baseStylesEnabled ? '1' : ''];
        if ($this->connection->fetchOne('SELECT id FROM tl_withdrawal_settings WHERE id = 1')) {
            $this->connection->update('tl_withdrawal_settings', $data, ['id' => 1]);
        } else {
            $this->connection->insert('tl_withdrawal_settings', ['id' => 1, ...$data]);
        }
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
