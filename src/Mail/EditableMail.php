<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

/** The editable envelope of a mail. Contractual information is added separately. */
final class EditableMail
{
    public const FIELDS = ['subject', 'preheader', 'introduction', 'closing', 'greeting', 'signature'];

    private const TOKENS = [
        'shop_customer' => ['customer.name', 'order.number'],
        'shop_merchant' => ['customer.name', 'order.number'],
        'shop_paid' => ['customer.name', 'order.number'],
        'shop_dispatched' => ['customer.name', 'order.number'],
        'shop_canceled' => ['customer.name', 'order.number'],
        'seminar_confirmation' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'seminar_merchant' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'seminar_waiting' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'seminar_promotion' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'seminar_reminder' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'seminar_canceled' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'seminar_certificate' => ['customer.name', 'booking.reference', 'event.title', 'event.date'],
        'withdrawal_consumer' => ['customer.name', 'contract.reference'],
        'withdrawal_merchant' => ['customer.name', 'contract.reference'],
    ];

    /** @return list<string> */
    public static function kinds(string $bundle): array
    {
        return array_values(array_filter(array_keys(self::TOKENS), static fn (string $kind): bool => str_starts_with($kind, $bundle.'_')));
    }

    /** @return list<string> */
    public static function tokens(string $kind): array
    {
        return self::TOKENS[$kind] ?? throw new \InvalidArgumentException('Unknown mail type.');
    }

    /** @return array<string, string> */
    public static function defaults(string $kind, string $locale = 'de', string $voice = 'sie'): array
    {
        self::tokens($kind);
        if (!\in_array($locale, ['de', 'en'], true) || !\in_array($voice, ['sie', 'du'], true)) {
            throw new \InvalidArgumentException('Unknown mail language or salutation.');
        }
        $en = 'en' === $locale;
        $du = 'du' === $voice;
        $titles = [
            'shop_customer' => ['Ihre Bestellung {{ order.number }}', 'Your order {{ order.number }}'],
            'shop_merchant' => ['Neue Bestellung {{ order.number }}', 'New order {{ order.number }}'],
            'shop_paid' => ['Zahlung zu {{ order.number }} eingegangen', 'Payment received for {{ order.number }}'],
            'shop_dispatched' => ['Ihre Bestellung {{ order.number }} ist unterwegs', 'Your order {{ order.number }} is on its way'],
            'shop_canceled' => ['Storno zu {{ order.number }}', 'Cancellation of {{ order.number }}'],
            'seminar_confirmation' => ['Ihre Buchung {{ booking.reference }}', 'Your booking {{ booking.reference }}'],
            'seminar_merchant' => ['Neue Buchung {{ booking.reference }}', 'New booking {{ booking.reference }}'],
            'seminar_waiting' => ['Warteliste für {{ event.title }}', 'Waiting list for {{ event.title }}'],
            'seminar_promotion' => ['Ein Platz für {{ event.title }} ist frei', 'A place for {{ event.title }} is available'],
            'seminar_reminder' => ['Erinnerung an {{ event.title }}', 'Reminder for {{ event.title }}'],
            'seminar_canceled' => ['Absage Ihrer Buchung {{ booking.reference }}', 'Cancellation of your booking {{ booking.reference }}'],
            'seminar_certificate' => ['Ihre Teilnahmebestätigung', 'Your certificate of attendance'],
            'withdrawal_consumer' => ['Eingangsbestätigung Ihres Widerrufs', 'Confirmation of your withdrawal'],
            'withdrawal_merchant' => ['Neuer Widerruf', 'New withdrawal'],
        ];
        $intro = [
            'shop_customer' => ['vielen Dank für Ihre Bestellung. Hier finden Sie die Angaben zu Ihrem Vertrag.', 'thank you for your order. Your contract details follow.'],
            'shop_merchant' => ['eine neue Bestellung ist eingegangen.', 'a new order has arrived.'],
            'shop_paid' => ['Ihre Zahlung ist eingegangen. Die Rechnung finden Sie im Anhang.', 'we have received your payment. Your invoice is attached.'],
            'shop_dispatched' => ['Ihre Bestellung ist versendet beziehungsweise abholbereit.', 'your order has been dispatched or is ready for collection.'],
            'shop_canceled' => ['Ihre Bestellung wurde storniert. Eine Stornorechnung finden Sie gegebenenfalls im Anhang.', 'your order has been canceled. A cancellation invoice is attached where applicable.'],
            'seminar_confirmation' => ['vielen Dank für Ihre Buchung. Die verbindlichen Angaben folgen unten.', 'thank you for booking. Your binding booking details follow.'],
            'seminar_merchant' => ['eine neue Seminarbuchung ist eingegangen.', 'a new seminar booking has arrived.'],
            'seminar_waiting' => ['Sie stehen auf der Warteliste. Wir melden uns, sobald ein Platz frei wird.', 'you are on the waiting list. We will contact you when a place opens.'],
            'seminar_promotion' => ['ein Platz ist frei geworden. Die Angaben zu Ihrer Buchung folgen unten.', 'a place has opened. Your booking details follow.'],
            'seminar_reminder' => ['Ihr Seminar beginnt bald. Wir freuen uns auf Sie.', 'your seminar starts soon. We look forward to seeing you.'],
            'seminar_canceled' => ['Ihre Buchung wurde storniert.', 'your booking has been canceled.'],
            'seminar_certificate' => ['vielen Dank für Ihre Teilnahme. Ihre Bestätigung finden Sie im Anhang.', 'thank you for attending. Your certificate is attached.'],
            'withdrawal_consumer' => ['wir bestätigen den Eingang Ihres Widerrufs.', 'we confirm receipt of your withdrawal.'],
            'withdrawal_merchant' => ['ein Widerruf ist eingegangen.', 'a withdrawal has been received.'],
        ];
        $duTitles = [
            'shop_customer' => 'Deine Bestellung {{ order.number }}',
            'shop_dispatched' => 'Deine Bestellung {{ order.number }} ist unterwegs',
            'seminar_confirmation' => 'Deine Buchung {{ booking.reference }}',
            'seminar_reminder' => 'Erinnerung an {{ event.title }}',
            'seminar_canceled' => 'Absage deiner Buchung {{ booking.reference }}',
            'seminar_certificate' => 'Deine Teilnahmebestätigung',
            'withdrawal_consumer' => 'Eingangsbestätigung deines Widerrufs',
        ];
        $duIntro = [
            'shop_customer' => 'vielen Dank für deine Bestellung. Hier findest du die Angaben zu deinem Vertrag.',
            'shop_paid' => 'Deine Zahlung ist eingegangen. Die Rechnung findest du im Anhang.',
            'shop_dispatched' => 'Deine Bestellung ist versendet beziehungsweise abholbereit.',
            'shop_canceled' => 'Deine Bestellung wurde storniert. Eine Stornorechnung findest du gegebenenfalls im Anhang.',
            'seminar_confirmation' => 'vielen Dank für deine Buchung. Die verbindlichen Angaben folgen unten.',
            'seminar_waiting' => 'Du stehst auf der Warteliste. Wir melden uns, sobald ein Platz frei wird.',
            'seminar_promotion' => 'ein Platz ist frei geworden. Die Angaben zu deiner Buchung folgen unten.',
            'seminar_reminder' => 'Dein Seminar beginnt bald. Wir freuen uns auf dich.',
            'seminar_canceled' => 'Deine Buchung wurde storniert.',
            'seminar_certificate' => 'vielen Dank für deine Teilnahme. Deine Bestätigung findest du im Anhang.',
            'withdrawal_consumer' => 'wir bestätigen den Eingang deines Widerrufs.',
        ];
        $title = $du && !$en ? ($duTitles[$kind] ?? $titles[$kind][0]) : $titles[$kind][$en ? 1 : 0];
        $opening = $du && !$en ? ($duIntro[$kind] ?? $intro[$kind][0]) : $intro[$kind][$en ? 1 : 0];

        return [
            'subject' => $title,
            'preheader' => $opening,
            'introduction' => ($en ? 'Hello' : ($du ? 'Hallo' : 'Guten Tag'))." {{ customer.name }},\n\n".$opening,
            'closing' => $en ? 'Please contact us if you have any questions.' : ($du ? 'Bei Fragen antworte gern auf diese E-Mail.' : 'Bei Fragen antworten Sie gern auf diese E-Mail.'),
            'greeting' => $en ? 'Kind regards' : 'Freundliche Grüße',
            'signature' => '',
        ];
    }

    /** @param array<string, mixed> $overrides
     *  @return array<string, string>
     */
    public static function resolve(string $kind, string $locale, string $voice, array $overrides): array
    {
        $fields = self::defaults($kind, $locale, $voice);
        foreach ($overrides as $field => $value) {
            if (!\in_array($field, self::FIELDS, true) || !\is_string($value)) {
                throw new \InvalidArgumentException('Unknown mail field.');
            }
            self::validate($kind, $field, $value);
            $fields[$field] = $value;
        }

        return $fields;
    }

    public static function validate(string $kind, string $field, string $value): void
    {
        if (!\in_array($field, self::FIELDS, true)) {
            throw new \InvalidArgumentException('Unknown mail field.');
        }
        if (str_contains($value, '{%') || str_contains($value, '{#') || str_contains($value, '#}') || str_contains($value, '%}')) {
            throw new \InvalidArgumentException('Twig code is not allowed in mail fields.');
        }
        $withoutTokens = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/u', static function (array $match) use ($kind): string {
            if (!\in_array(trim($match[1]), self::tokens($kind), true)) {
                throw new \InvalidArgumentException('Unknown mail placeholder: '.$match[1]);
            }

            return '';
        }, $value);
        if (null === $withoutTokens || str_contains($withoutTokens, '{{') || str_contains($withoutTokens, '}}')) {
            throw new \InvalidArgumentException('Invalid mail placeholder.');
        }
        if ('subject' === $field && preg_match('/[\r\n\x00-\x1f\x7f]/', $value)) {
            throw new \InvalidArgumentException('The mail subject must be one line.');
        }
    }

    /** @param array<string, string> $values */
    public static function expand(string $kind, string $field, string $value, array $values): string
    {
        self::validate($kind, $field, $value);

        $expanded = (string) preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/u', static function (array $match) use ($kind, $values): string {
            $token = trim($match[1]);
            if (!\array_key_exists($token, $values) || !\in_array($token, self::tokens($kind), true)) {
                throw new \InvalidArgumentException('Missing mail placeholder value: '.$token);
            }

            return $values[$token];
        }, $value);

        return 'subject' === $field ? self::header($expanded) : $expanded;
    }

    public static function header(string $value): string
    {
        if (preg_match('/[\r\n\x00-\x1f\x7f]/', $value)) {
            throw new \InvalidArgumentException('Invalid mail header.');
        }

        return $value;
    }
}
