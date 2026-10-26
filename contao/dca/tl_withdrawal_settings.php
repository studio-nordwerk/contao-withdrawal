<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_withdrawal_settings'] = [
    'config' => ['sql' => ['keys' => ['id' => 'primary']]],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default '0'"],
        'merchantEmail' => ['sql' => "varchar(255) NOT NULL default ''"],
        'path' => ['sql' => "varchar(255) NOT NULL default '/withdrawal'"],
        'baseStylesEnabled' => ['sql' => "char(1) NOT NULL default '1'"],
        'mailOptions' => ['sql' => 'longtext NULL'],
    ],
];
