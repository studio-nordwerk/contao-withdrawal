<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Backend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

final class WithdrawalTime
{
    #[AsCallback(table: 'tl_withdrawal', target: 'fields.submittedAt.load')]
    #[AsCallback(table: 'tl_withdrawal', target: 'fields.confirmationSentAt.load')]
    #[AsCallback(table: 'tl_withdrawal', target: 'fields.merchantSentAt.load')]
    public function load(string|null $value): string
    {
        return self::format($value);
    }

    public static function format(string|null $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Europe/Berlin'))
            ->format('Y-m-d H:i:s.u P').' (Europe/Berlin)'
        ;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $data
     * @param array<string, mixed>                      $row
     *
     * @return array<string, list<array<string, mixed>>>
     */
    #[AsCallback(table: 'tl_withdrawal', target: 'config.onshow')]
    public function show(array $data, array $row): array
    {
        foreach ($data['tl_withdrawal'][0] ?? [] as $label => $value) {
            foreach (['submittedAt', 'confirmationSentAt', 'merchantSentAt'] as $field) {
                if (str_ends_with($label, '<small>'.$field.'</small>')) {
                    $data['tl_withdrawal'][0][$label] = self::format($row[$field]);
                }
            }
        }

        return $data;
    }
}
