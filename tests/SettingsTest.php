<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Nordwerk\WithdrawalBundle\Settings\WithdrawalSettings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testBackendValuesAndOptionalEnvironmentOverrides(): void
    {
        $url = (string) (getenv('DATABASE_URL') ?: 'mysql://contao:contao@db:3306/contao');
        $parts = parse_url($url);
        $this->assertIsArray($parts);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql', 'host' => $parts['host'] ?? 'db', 'port' => $parts['port'] ?? 3306,
            'user' => $parts['user'] ?? 'contao', 'password' => $parts['pass'] ?? 'contao',
            'dbname' => ltrim($parts['path'] ?? '/contao', '/'),
        ]);
        $settings = new WithdrawalSettings($connection);
        $before = $settings->stored();
        $emailOverride = $_SERVER['WITHDRAWAL_MERCHANT_EMAIL'] ?? null;
        $pathOverride = $_SERVER['WITHDRAWAL_PATH'] ?? null;

        try {
            unset($_SERVER['WITHDRAWAL_MERCHANT_EMAIL'], $_SERVER['WITHDRAWAL_PATH']);
            $settings->save('merchant@example.test', '/service/widerruf');
            $this->assertSame('merchant@example.test', $settings->merchantEmail());
            $this->assertSame('/service/widerruf', $settings->path());
            $_SERVER['WITHDRAWAL_MERCHANT_EMAIL'] = 'override@example.test';
            $_SERVER['WITHDRAWAL_PATH'] = '/override';
            $this->assertSame('override@example.test', $settings->merchantEmail());
            $this->assertSame('/override', $settings->path());
            $this->expectException(\InvalidArgumentException::class);
            $settings->save("evil@example.test\r\nBcc: attacker@example.test", '/withdrawal');
        } finally {
            if (null === $emailOverride) {
                unset($_SERVER['WITHDRAWAL_MERCHANT_EMAIL']);
            } else {
                $_SERVER['WITHDRAWAL_MERCHANT_EMAIL'] = $emailOverride;
            }
            if (null === $pathOverride) {
                unset($_SERVER['WITHDRAWAL_PATH']);
            } else {
                $_SERVER['WITHDRAWAL_PATH'] = $pathOverride;
            }
            if ('' === $before['merchantEmail']) {
                $connection->delete('tl_withdrawal_settings', ['id' => 1]);
            } else {
                $settings->save($before['merchantEmail'], $before['path']);
            }
            $connection->close();
        }
    }
}
