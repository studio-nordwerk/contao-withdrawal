<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Nordwerk\WithdrawalBundle\Mail\EditableMail;
use Nordwerk\WithdrawalBundle\Mail\MailEditor;
use PHPUnit\Framework\TestCase;

final class PaymentReminderMailTest extends TestCase
{
    public function testPaymentRemindersAreEditableInBothLanguages(): void
    {
        foreach (['shop', 'seminar'] as $bundle) {
            $kind = $bundle.'_payment_reminder';
            $this->assertContains($kind, EditableMail::kinds($bundle));

            foreach (['de', 'en'] as $locale) {
                $fields = EditableMail::defaults($kind, $locale);
                $fields['introduction'] = 'Hello {{ customer.name }}';
                $options = MailEditor::apply([], $bundle, $kind, $locale, ['mailAction' => 'save', ...$fields]);
                $this->assertSame('Hello {{ customer.name }}', $options['templates'][$locale][$kind]['introduction']);
            }
        }
    }
}
