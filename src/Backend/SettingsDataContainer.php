<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Backend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Doctrine\DBAL\Connection;
use Nordwerk\WithdrawalBundle\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Callbacks of the singleton settings record tl_withdrawal_settings (id 1).
 */
final readonly class SettingsDataContainer
{
    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
        private RouterInterface $router,
    ) {
    }

    /**
     * Opens the one record in the standard edit form and keeps old links to the mail
     * editor working.
     */
    #[AsCallback(table: 'tl_withdrawal_settings', target: 'config.onload')]
    public function openRecord(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || 'withdrawal_settings' !== $request->query->get('do') || $request->query->has('key') || $request->isXmlHttpRequest()) {
            return;
        }
        $action = $request->query->get('act');
        if (null !== $action && 'edit' !== $action) {
            return;
        }
        // The demo/setup scripts of the shop and seminar packages insert id 1
        // themselves; only create it on first use here.
        $this->connection->executeStatement(
            "INSERT IGNORE INTO tl_withdrawal_settings (id, tstamp, merchantEmail, path) VALUES (1, ?, '', '/withdrawal')",
            [time()],
        );
        if (null !== $action) {
            return;
        }
        if ($request->query->has('mail')) {
            throw new RedirectResponseException($this->router->generate('contao_backend', ['do' => 'withdrawal_settings', 'key' => 'mails', 'mail' => (string) $request->query->get('mail'), 'locale' => 'en' === $request->query->get('locale') ? 'en' : 'de']));
        }

        throw new RedirectResponseException($this->router->generate('contao_backend', ['do' => 'withdrawal_settings', 'act' => 'edit', 'id' => 1]));
    }

    #[AsCallback(table: 'tl_withdrawal_settings', target: 'fields.merchantEmail.save')]
    public function validateMerchantEmail(mixed $value): string
    {
        $email = trim((string) $value);
        if (!WithdrawalSettings::isValidMerchantEmail($email)) {
            throw new \RuntimeException($GLOBALS['TL_LANG']['tl_withdrawal_settings']['invalidMerchantEmail']);
        }

        return $email;
    }

    #[AsCallback(table: 'tl_withdrawal_settings', target: 'fields.path.save')]
    public function validatePath(mixed $value): string
    {
        $path = trim((string) $value);
        if (!WithdrawalSettings::isValidPath($path)) {
            throw new \RuntimeException($GLOBALS['TL_LANG']['tl_withdrawal_settings']['invalidPath']);
        }

        return $path;
    }

    #[AsCallback(table: 'tl_withdrawal_settings', target: 'fields.mailEditor.input_field')]
    public function mailEditorLink(): string
    {
        $url = $this->router->generate('contao_backend', ['do' => 'withdrawal_settings', 'key' => 'mails', 'mail' => 'withdrawal_consumer', 'locale' => 'de']);
        [$label, $help] = $GLOBALS['TL_LANG']['tl_withdrawal_settings']['mailEditor'];
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return '<div class="widget clr"><h3>'.$escape($label).'</h3><p><a class="tl_submit" href="'.$escape($url).'">'.$escape($GLOBALS['TL_LANG']['tl_withdrawal_settings']['openMailEditor']).'</a></p><p class="tl_help tl_tip">'.$escape($help).'</p></div>';
    }
}
