<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Nordwerk\WithdrawalBundle\Mail\EditableMail;
use Nordwerk\WithdrawalBundle\Mail\MailEditor;
use Nordwerk\WithdrawalBundle\Mail\MailOptions;
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

    public function testEveryMailTypeHasAReadableNameInTheEditor(): void
    {
        $preview = ['subject' => '', 'html' => '', 'text' => ''];

        foreach (['shop', 'seminar', 'withdrawal'] as $bundle) {
            foreach (EditableMail::kinds($bundle) as $kind) {
                foreach ([false, true] as $english) {
                    $html = MailEditor::html(MailOptions::defaults(), $bundle, $kind, 'de', 'contao?do=mails', 'token', $preview, english: $english);
                    $this->assertStringNotContainsString('>'.str_replace('_', ' ', $kind).'<', $html, $kind);
                    $this->assertStringNotContainsString(str_replace('_', ' ', $kind).' · ', $html, $kind);
                }
            }
        }
        $seminar = MailEditor::html(MailOptions::defaults(), 'seminar', 'seminar_payment_reminder', 'de', 'contao?do=mails', 'token', $preview);
        $this->assertStringContainsString('>Zahlungserinnerung<', $seminar);
        $this->assertStringContainsString('>Buchungsbestätigung<', $seminar);
    }

    public function testPartialContractWordingMatchesTheConfirmationInBothLanguages(): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__.'/../templates/mail'));

        foreach (['de' => 'Vertragsteil', 'en' => 'part of the contract'] as $locale => $part) {
            $messages = Yaml::parseFile(__DIR__.'/../translations/messages.'.$locale.'.yaml');
            $this->assertStringContainsString($part, $messages['withdrawal']['declaration']);

            foreach (['consumer', 'merchant'] as $recipient) {
                $body = $twig->render($recipient.('en' === $locale ? '.en' : '').'.txt.twig', ['name' => 'Ada', 'contractReference' => 'Only item 2 of order 123', 'email' => 'a@example.test', 'submittedAt' => new \DateTimeImmutable()]);
                $this->assertStringContainsString($messages['withdrawal']['declaration'], $body);
            }
        }
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
