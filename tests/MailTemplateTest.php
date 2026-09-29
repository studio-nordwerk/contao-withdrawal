<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Nordwerk\WithdrawalBundle\Mail\EditableMail;
use Nordwerk\WithdrawalBundle\Mail\MailDocument;
use Nordwerk\WithdrawalBundle\Mail\MailEditor;
use Nordwerk\WithdrawalBundle\Mail\MailOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailTemplateTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function mailKinds(): iterable
    {
        foreach (['shop', 'seminar', 'withdrawal'] as $bundle) {
            foreach (EditableMail::kinds($bundle) as $kind) {
                foreach (['de', 'en'] as $locale) {
                    yield [$kind, $locale];
                }
            }
        }
    }

    #[DataProvider('mailKinds')]
    public function testExampleMailMatchesHtmlAndTextSnapshots(string $kind, string $locale): void
    {
        $rendered = MailEditor::preview(new MailDocument(), MailOptions::defaults(), $kind, $locale);
        $path = __DIR__.'/snapshots/mail/'.$kind.'.'.$locale;
        $this->assertSame(file_get_contents($path.'.txt'), $rendered['text']);
        $this->assertSame(file_get_contents($path.'.html'), $rendered['html']);
        $this->assertStringContainsString('max-width: 600px', $rendered['html']);
        $this->assertStringContainsString('prefers-color-scheme: dark', $rendered['html']);
    }

    public function testPlaceholderValidationAndHeaderSafety(): void
    {
        $this->assertSame('Hallo Ada', EditableMail::expand('shop_customer', 'introduction', 'Hallo {{ customer.name }}', ['customer.name' => 'Ada']));

        foreach (['{{ customer.unknown }}', '{{ customer.name|raw }}', '{% if true %}x{% endif %}', '{{ customer.name'] as $unsafe) {
            try {
                EditableMail::validate('shop_customer', 'introduction', $unsafe);
                $this->fail('Unsafe placeholder was accepted.');
            } catch (\InvalidArgumentException) {
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        EditableMail::expand('shop_customer', 'subject', '{{ customer.name }}', ['customer.name' => "Ada\r\nBcc: other@example.test"]);
    }

    public function testEmptyEditableFieldsCannotRemoveFixedDetails(): void
    {
        $fields = array_fill_keys(EditableMail::FIELDS, '');
        $mail = (new MailDocument())->render('withdrawal_consumer', 'de', $fields, [], [['title' => 'Eingang', 'text' => '29.09.2026 14:34:56 Uhr']], 'Nordwerk');
        $this->assertStringContainsString('29.09.2026 14:34:56 Uhr', $mail['text']);
        $this->assertStringContainsString('29.09.2026 14:34:56 Uhr', $mail['html']);
        $this->expectException(\InvalidArgumentException::class);
        (new MailDocument())->render('withdrawal_consumer', 'de', $fields, [], [], 'Nordwerk');
    }

    public function testUnknownTemplateCannotBeSavedAndResetRestoresDefault(): void
    {
        $options = MailOptions::defaults();
        $options['templates']['de']['withdrawal_consumer']['subject'] = 'Eigener Betreff';
        $reset = MailEditor::apply($options, 'withdrawal', 'withdrawal_consumer', 'de', ['mailAction' => 'reset', 'resetField' => 'subject']);
        $this->assertSame(EditableMail::defaults('withdrawal_consumer')['subject'], MailOptions::fields($reset, 'withdrawal_consumer', 'de')['subject']);
        $options['templates']['de']['withdrawal_consumer']['subject'] = '{{ unknown }}';
        $this->expectException(\InvalidArgumentException::class);
        MailOptions::validate($options, 'withdrawal');
    }

    public function testHeaderInjectionInSenderIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MailOptions::validate(['senderName' => "Nordwerk\r\nBcc: other@example.test"], 'withdrawal');
    }

    public function testDuVoiceUsesInformalDefaultCopy(): void
    {
        $fields = EditableMail::defaults('shop_customer', 'de', 'du');
        $this->assertSame('Deine Bestellung {{ order.number }}', $fields['subject']);
        $this->assertStringContainsString('Hier findest du', $fields['introduction']);
        $this->assertStringNotContainsString('Ihre Bestellung', $fields['introduction']);
    }
}
