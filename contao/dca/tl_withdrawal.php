<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_withdrawal'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'notCopyable' => true,
        'notCreatable' => true,
        'sql' => ['keys' => ['id' => 'primary', 'submissionToken' => 'unique', 'mailStatus' => 'index']],
    ],
    'list' => [
        'sorting' => ['mode' => 1, 'fields' => ['id'], 'flag' => 12, 'panelLayout' => 'filter;search,limit'],
        'label' => ['fields' => ['submittedAt', 'consumerName', 'contractReference', 'status'], 'format' => '%s | %s | %s | %s'],
        'global_operations' => ['all' => ['href' => 'act=select', 'class' => 'header_edit_all', 'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"']],
        'operations' => ['edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'], 'show' => ['href' => 'act=show', 'icon' => 'show.svg']],
    ],
    'palettes' => ['default' => '{withdrawal_legend},consumerName,contractReference,email,submittedAt;{review_legend},status;{mail_legend},mailStatus,confirmationSentAt,merchantSentAt'],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'submissionToken' => ['sql' => "varchar(64) NOT NULL default ''"],
        'consumerName' => ['inputType' => 'text', 'eval' => ['readonly' => true], 'sql' => "varchar(255) NOT NULL default ''"],
        'contractReference' => ['inputType' => 'textarea', 'eval' => ['readonly' => true], 'sql' => 'text NOT NULL'],
        'email' => ['inputType' => 'text', 'eval' => ['readonly' => true], 'sql' => "varchar(255) NOT NULL default ''"],
        'locale' => ['sql' => "varchar(2) NOT NULL default 'de'"],
        'submittedAt' => ['inputType' => 'text', 'eval' => ['readonly' => true], 'sql' => "varchar(32) NOT NULL default ''"],
        'confirmationSentAt' => ['inputType' => 'text', 'eval' => ['readonly' => true], 'sql' => 'varchar(32) DEFAULT NULL'],
        'merchantSentAt' => ['inputType' => 'text', 'eval' => ['readonly' => true], 'sql' => 'varchar(32) DEFAULT NULL'],
        'mailStatus' => ['inputType' => 'text', 'eval' => ['readonly' => true], 'sql' => "varchar(16) NOT NULL default 'pending'"],
        'status' => ['inputType' => 'select', 'options' => ['new', 'reviewed', 'done'], 'reference' => &$GLOBALS['TL_LANG']['tl_withdrawal']['status_options'], 'eval' => ['includeBlankOption' => false], 'sql' => "varchar(16) NOT NULL default 'new'"],
    ],
];
