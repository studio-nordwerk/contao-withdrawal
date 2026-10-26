<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_withdrawal_settings']['delivery_legend'] = 'Delivery and link';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['layout_legend'] = 'Appearance';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mail_legend'] = 'Mail texts';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['merchantEmail'] = ['Merchant email for withdrawals', 'Every received withdrawal declaration is sent to this address. The environment variable WITHDRAWAL_MERCHANT_EMAIL optionally overrides it.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['path'] = ['Public path of the withdrawal function', 'For example /withdrawal or /service/widerruf. The page must be published and unprotected. The environment variable WITHDRAWAL_PATH optionally overrides it.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['baseStylesEnabled'] = ['Enable base styling', 'Fields, buttons and spacing matching the website. Font and colours come from the theme; adjust them with the --nw-* CSS variables. Switch it off if the theme styles the form itself.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mailEditor'] = ['Mail texts and preview', 'Subject, introduction and sender of the confirmation and merchant mail; mandatory details are added on sending.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['openMailEditor'] = 'Edit mail texts';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mails'] = ['Mail texts', 'Mail texts and preview'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['edit'] = 'Edit settings';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['show'] = 'Show settings';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['invalidMerchantEmail'] = 'Please enter a valid merchant email address.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['invalidPath'] = 'The path must start with a single / and must not contain a query (?) or fragment (#).';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['testMailNoAddress'] = 'Your backend account needs a valid email address.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['testMailSent'] = 'Test mail sent to %s.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mailSaved'] = 'Mail settings saved.';
