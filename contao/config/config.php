<?php

declare(strict_types=1);

use Nordwerk\WithdrawalBundle\Backend\SettingsModule;

$GLOBALS['BE_MOD']['content']['withdrawals'] = ['tables' => ['tl_withdrawal']];
$GLOBALS['BE_MOD']['content']['withdrawal_settings'] = ['callback' => SettingsModule::class];
