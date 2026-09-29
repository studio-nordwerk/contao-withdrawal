<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Backend;

use Contao\BackendModule;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Input;
use Contao\System;
use Nordwerk\WithdrawalBundle\Mail\EditableMail;
use Nordwerk\WithdrawalBundle\Mail\MailDocument;
use Nordwerk\WithdrawalBundle\Mail\MailEditor;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Settings\WithdrawalSettings;

/**
 * @property BackendTemplate $Template
 */
final class SettingsModule extends BackendModule
{
    protected $strTemplate = 'be_withdrawal_settings';

    protected function compile(): void
    {
        /** @var WithdrawalSettings $settings */
        $settings = System::getContainer()->get(WithdrawalSettings::class);
        if (\is_string(Input::get('mail'))) {
            $this->compileMail($settings);

            return;
        }
        $values = $settings->stored();
        $error = '';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            try {
                $emailInput = Input::post('merchantEmail');
                $pathInput = Input::post('path');
                $email = \is_string($emailInput) ? trim($emailInput) : '';
                $path = \is_string($pathInput) ? trim($pathInput) : '';
                $settings->save($email, $path, '1' === Input::post('baseStylesEnabled'));
                Controller::redirect('contao?do=withdrawal_settings&saved=1');
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
                $values = ['merchantEmail' => $email, 'path' => $path, 'baseStylesEnabled' => '1' === Input::post('baseStylesEnabled')];
            }
        }
        $this->Template->setData(['values' => $values, 'error' => $error, 'saved' => '1' === Input::get('saved')]);
    }

    private function compileMail(WithdrawalSettings $settings): void
    {
        $rawKind = Input::get('mail');
        $kind = \is_string($rawKind) ? $rawKind : '';
        $locale = 'en' === Input::get('locale') ? 'en' : 'de';
        if (!\in_array($kind, EditableMail::kinds('withdrawal'), true)) {
            $kind = 'withdrawal_consumer';
        }
        $options = $settings->mailOptions();
        $notice = '';
        $error = '';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            try {
                $rawAction = Input::post('mailAction');
                $action = \is_string($rawAction) ? $rawAction : '';
                if ('test' === $action) {
                    $recipient = (string) BackendUser::getInstance()->email;
                    if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException('Ihr Backend-Konto benötigt eine gültige E-Mail-Adresse.');
                    }
                    /** @var WithdrawalMailer $mailer */
                    $mailer = System::getContainer()->get(WithdrawalMailer::class);
                    $mailer->sendTest($kind, $locale, $recipient, $options);
                    $notice = 'Testmail wurde an '.$recipient.' gesendet.';
                } else {
                    $posted = ['mailAction' => $action];

                    foreach ([...EditableMail::FIELDS, 'resetField', 'senderName', 'senderAddress', 'replyTo', 'accent', 'logoPath', 'voice'] as $field) {
                        $value = Input::post($field);
                        if (\is_string($value)) {
                            $posted[$field] = $value;
                        }
                    }
                    $options = MailEditor::apply($options, 'withdrawal', $kind, $locale, $posted);
                    $settings->saveMailOptions($options);
                    $notice = 'Mail-Einstellungen gespeichert.';
                }
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        /** @var MailDocument $document */
        $document = System::getContainer()->get(MailDocument::class);
        $preview = MailEditor::preview($document, $options, $kind, $locale);
        /** @var ContaoCsrfTokenManager $tokenManager */
        $tokenManager = System::getContainer()->get('contao.csrf.token_manager');
        $this->Template->setData([
            'mailEditor' => MailEditor::html($options, 'withdrawal', $kind, $locale, 'contao?do=withdrawal_settings', $tokenManager->getDefaultTokenValue(), $preview, $notice, $error),
        ]);
    }
}
