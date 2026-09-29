<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Backend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_withdrawal', target: 'list.label.label_callback')]
final class WithdrawalLabel
{
    /**
     * @param array<string, mixed> $row
     * @param list<string|null>    $args
     */
    public function __invoke(array $row, string $label, DataContainer $dc, array $args): string
    {
        return htmlspecialchars(implode(' | ', $args), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
