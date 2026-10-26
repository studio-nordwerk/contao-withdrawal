<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * One settings record (id 1) for the whole installation. The backend module "withdrawal_settings" opens it
 * directly in the standard edit form; the mail texts have their own view under the same module (key=mails).
 * Access is controlled by the module permission alone, as before this record had a DCA: the fields are not
 * excluded and the table opts out of the create/update/delete permissions (there is only this one record).
 * Otherwise existing groups with the module would lose access after the update.
 */
$GLOBALS['TL_DCA']['tl_withdrawal_settings'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'notCreatable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'permissions' => [],
        'sql' => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_UNSORTED],
        'label' => ['fields' => ['merchantEmail', 'path'], 'format' => '%s · %s'],
        'operations' => ['edit', 'show'],
    ],
    'palettes' => [
        'default' => '{delivery_legend},merchantEmail,path;{layout_legend},baseStylesEnabled;{mail_legend},mailEditor',
    ],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default '0'"],
        'merchantEmail' => [
            'exclude' => false,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'path' => [
            'exclude' => false,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default '/withdrawal'",
        ],
        'baseStylesEnabled' => [
            'exclude' => false,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr'],
            'sql' => "char(1) NOT NULL default '1'",
        ],
        // Shown as a link to the mail view; not a stored value.
        'mailEditor' => ['exclude' => false],
        // Maintained in the mail view (key=mails), so restoring a settings version must
        // not roll back the mail texts.
        'mailOptions' => ['eval' => ['versionize' => false], 'sql' => 'longtext NULL'],
    ],
];
