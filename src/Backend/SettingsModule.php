<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Backend;

use Contao\BackendModule;
use Contao\BackendTemplate;
use Contao\Controller;
use Contao\Input;
use Contao\System;
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
        $values = $settings->stored();
        $error = '';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            try {
                $email = trim((string) Input::post('merchantEmail'));
                $path = trim((string) Input::post('path'));
                $settings->save($email, $path);
                Controller::redirect('contao?do=withdrawal_settings&saved=1');
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
                $values = ['merchantEmail' => $email, 'path' => $path];
            }
        }
        $this->Template->setData(['values' => $values, 'error' => $error, 'saved' => '1' === Input::get('saved')]);
    }
}
