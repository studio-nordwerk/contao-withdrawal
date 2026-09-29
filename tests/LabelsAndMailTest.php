<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class LabelsAndMailTest extends TestCase
{
    public function testExactGermanLabels(): void
    {
        /** @var array<string, mixed> $messages */
        $messages = Yaml::parseFile(__DIR__.'/../translations/messages.de.yaml');
        $this->assertSame('Vertrag widerrufen', $messages['withdrawal']['title']);
        $this->assertSame('Widerruf bestätigen', $messages['withdrawal']['confirm']);
    }

    public function testMailContainsDeclarationAndBerlinReceiptTime(): void
    {
        $loader = new FilesystemLoader(__DIR__.'/../templates/mail');
        $twig = new Environment($loader);
        $body = $twig->render('consumer.txt.twig', [
            'name' => 'Ada Example',
            'contractReference' => 'ORDER-123',
            'email' => 'ada@example.test',
            'submittedAt' => new \DateTimeImmutable('2026-09-29 12:34:56', new \DateTimeZone('UTC')),
        ]);

        $this->assertStringContainsString('Ada Example', $body);
        $this->assertStringContainsString('ORDER-123', $body);
        $this->assertStringContainsString('29.09.2026 14:34:56 Uhr (Europe/Berlin)', $body);
        $this->assertStringContainsString('Hiermit widerrufe ich', $body);
    }
}
