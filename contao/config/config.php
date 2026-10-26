<?php

declare(strict_types=1);

use Nordwerk\WithdrawalBundle\Backend\MailSettingsView;

$GLOBALS['BE_MOD']['content']['withdrawals'] = ['tables' => ['tl_withdrawal']];
// The settings record opens in the standard edit form; "key=mails" is the mail
// text view under the same module permission.
$GLOBALS['BE_MOD']['content']['withdrawal_settings'] = ['tables' => ['tl_withdrawal_settings'], 'mails' => [MailSettingsView::class, 'run']];
