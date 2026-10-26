<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_withdrawal_settings']['delivery_legend'] = 'Versand und Link';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['layout_legend'] = 'Darstellung';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mail_legend'] = 'Mailtexte';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['merchantEmail'] = ['Händleradresse für Widerrufsmails', 'An diese Adresse geht jede eingegangene Widerrufserklärung. Die Umgebungsvariable WITHDRAWAL_MERCHANT_EMAIL überschreibt den Wert optional.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['path'] = ['Öffentlicher Pfad der Widerrufsfunktion', 'Zum Beispiel /withdrawal oder /service/widerruf. Die Seite muss veröffentlicht und ungeschützt sein. Die Umgebungsvariable WITHDRAWAL_PATH überschreibt den Wert optional.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['baseStylesEnabled'] = ['Grundgestaltung einschalten', 'Felder, Buttons und Abstände passend zur Website. Schrift und Farben kommen vom Theme; Anpassungen über die CSS-Variablen --nw-*. Ausschalten, wenn das Theme das Formular selbst gestaltet.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mailEditor'] = ['Mailtexte und Vorschau', 'Betreff, Einleitung und Absender der Bestätigungs- und Händlermail; feste Pflichtangaben ergänzt der Versand.'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['openMailEditor'] = 'Mailtexte bearbeiten';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mails'] = ['Mailtexte', 'Mailtexte und Vorschau'];
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['edit'] = 'Einstellungen bearbeiten';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['show'] = 'Einstellungen anzeigen';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['invalidMerchantEmail'] = 'Bitte eine gültige Händleradresse eingeben.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['invalidPath'] = 'Der Pfad muss mit einem einzelnen / beginnen und darf weder Query (?) noch Fragment (#) enthalten.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['testMailNoAddress'] = 'Ihr Backend-Konto benötigt eine gültige E-Mail-Adresse.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['testMailSent'] = 'Testmail wurde an %s gesendet.';
$GLOBALS['TL_LANG']['tl_withdrawal_settings']['mailSaved'] = 'Mail-Einstellungen gespeichert.';
