<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Backend;

use Contao\BackendUser;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Nordwerk\WithdrawalBundle\Mail\EditableMail;
use Nordwerk\WithdrawalBundle\Mail\MailDocument;
use Nordwerk\WithdrawalBundle\Mail\MailEditor;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Settings\WithdrawalSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Mail texts and preview, opened as "do=withdrawal_settings&key=mails". Contao
 * only dispatches the key after the module permission "withdrawal_settings" has
 * been checked, so this view needs no permission logic of its own.
 */
#[Autoconfigure(public: true)]
final readonly class MailSettingsView
{
    public function __construct(
        private WithdrawalSettings $settings,
        private WithdrawalMailer $mailer,
        private MailDocument $document,
        private ContaoCsrfTokenManager $tokenManager,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private Security $security,
    ) {
    }

    public function run(): string
    {
        $lang = $GLOBALS['TL_LANG']['tl_withdrawal_settings'];
        $request = $this->requestStack->getCurrentRequest() ?? throw new \RuntimeException('No request.');
        $kind = (string) $request->query->get('mail', '');
        $locale = 'en' === $request->query->get('locale') ? 'en' : 'de';
        if (!\in_array($kind, EditableMail::kinds('withdrawal'), true)) {
            $kind = 'withdrawal_consumer';
        }
        $options = $this->settings->mailOptions();
        $notice = '';
        $error = '';
        if ($request->isMethod('POST')) {
            try {
                // Raw values: Contao's Input::post() would HTML-encode "#", "(" or "=" in
                // colours and mail texts; MailOptions validates them and the mail templates
                // escape on output.
                $action = (string) $request->request->get('mailAction', '');
                if ('test' === $action) {
                    $user = $this->security->getUser();
                    $recipient = $user instanceof BackendUser ? (string) $user->email : '';
                    if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException($lang['testMailNoAddress']);
                    }
                    $this->mailer->sendTest($kind, $locale, $recipient, $options);
                    $notice = \sprintf($lang['testMailSent'], $recipient);
                } else {
                    $posted = ['mailAction' => $action];

                    foreach ([...EditableMail::FIELDS, 'resetField', 'senderName', 'senderAddress', 'replyTo', 'accent', 'logoPath', 'voice'] as $field) {
                        $value = $request->request->all()[$field] ?? null;
                        if (\is_string($value)) {
                            $posted[$field] = $value;
                        }
                    }
                    $options = MailEditor::apply($options, 'withdrawal', $kind, $locale, $posted);
                    $this->settings->saveMailOptions($options);
                    $notice = $lang['mailSaved'];
                }
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $preview = MailEditor::preview($this->document, $options, $kind, $locale);
        $action = $this->router->generate('contao_backend', ['do' => 'withdrawal_settings', 'key' => 'mails']);
        $back = $this->router->generate('contao_backend', ['do' => 'withdrawal_settings']);

        return MailEditor::html($options, 'withdrawal', $kind, $locale, $action, $this->tokenManager->getDefaultTokenValue(), $preview, $notice, $error, $back);
    }
}
