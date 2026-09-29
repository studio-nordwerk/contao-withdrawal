<?php

declare(strict_types=1);

require __DIR__.'/../app/vendor/autoload.php';

use Nordwerk\WithdrawalBundle\Mail\EditableMail;
use Nordwerk\WithdrawalBundle\Mail\MailDocument;
use Nordwerk\WithdrawalBundle\Mail\MailEditor;
use Nordwerk\WithdrawalBundle\Mail\MailOptions;

$folder = __DIR__.'/../tests/snapshots/mail';
if (!is_dir($folder)) {
    mkdir($folder, 0775, true);
}

foreach (['shop', 'seminar', 'withdrawal'] as $bundle) {
    foreach (EditableMail::kinds($bundle) as $kind) {
        foreach (['de', 'en'] as $locale) {
            $mail = MailEditor::preview(new MailDocument(), MailOptions::defaults(), $kind, $locale);
            file_put_contents($folder.'/'.$kind.'.'.$locale.'.txt', $mail['text']);
            file_put_contents($folder.'/'.$kind.'.'.$locale.'.html', $mail['html']);
        }
    }
}
