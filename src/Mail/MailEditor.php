<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

/**
 * Small Contao-independent editor shared by the three settings screens.
 */
final class MailEditor
{
    private const LABELS = [
        'de' => [
            'intro' => 'Pflegbare Texte; feste Pflichtangaben werden beim Versand aus dem Snapshot ergänzt.',
            'back' => 'Zurück zu den Einstellungen',
            'kind' => 'Mailtyp',
            'identity' => 'Absender und Darstellung',
            'senderName' => 'Absendername',
            'senderNameHelp' => 'Erscheint als Absender, z. B. der Name des Shops.',
            'senderAddress' => 'Absenderadresse',
            'senderAddressHelp' => 'Eine Adresse Ihrer Domain, damit die Mails ankommen (SPF/DKIM).',
            'replyTo' => 'Antwort an',
            'replyToHelp' => 'Antworten der Empfänger gehen an diese Adresse.',
            'accent' => 'Akzentfarbe (#RRGGBB)',
            'accentHelp' => 'Farbe für Überschriften und Buttons, z. B. #2f5d50.',
            'logoPath' => 'Logo unter files/',
            'logoPathHelp' => 'Pfad zu einer Bilddatei, z. B. files/logo.png.',
            'voice' => 'Anrede',
            'voiceHelp' => 'Sie oder Du in den Standardtexten.',
            'saveIdentity' => 'Absender speichern',
            'subject' => 'Betreff',
            'preheader' => 'Vorschautext',
            'introduction' => 'Anrede und Einleitung',
            'closing' => 'Schlusstext',
            'greeting' => 'Grußformel',
            'signature' => 'Signatur',
            'tokens' => 'Platzhalter:',
            'default' => 'Standard:',
            'reset' => 'Auf Standard zurücksetzen',
            'saveTexts' => 'Texte speichern',
            'preview' => 'Vorschau mit Beispieldaten',
            'previewTitle' => 'Mailvorschau',
            'textPart' => 'Textteil',
            'test' => 'Testmail an mich senden',
        ],
        'en' => [
            'intro' => 'Editable texts; fixed mandatory information is added from the snapshot when sending.',
            'back' => 'Back to the settings',
            'kind' => 'Mail type',
            'identity' => 'Sender and appearance',
            'senderName' => 'Sender name',
            'senderNameHelp' => 'Shown as the sender, e.g. the name of the shop.',
            'senderAddress' => 'Sender address',
            'senderAddressHelp' => 'An address of your domain, so the mails arrive (SPF/DKIM).',
            'replyTo' => 'Reply to',
            'replyToHelp' => 'Replies of the recipients go to this address.',
            'accent' => 'Accent colour (#RRGGBB)',
            'accentHelp' => 'Colour of headlines and buttons, e.g. #2f5d50.',
            'logoPath' => 'Logo in files/',
            'logoPathHelp' => 'Path to an image file, e.g. files/logo.png.',
            'voice' => 'Form of address',
            'voiceHelp' => 'Formal "Sie" or informal "Du" in the default texts.',
            'saveIdentity' => 'Save sender',
            'subject' => 'Subject',
            'preheader' => 'Preview text',
            'introduction' => 'Salutation and introduction',
            'closing' => 'Closing text',
            'greeting' => 'Greeting',
            'signature' => 'Signature',
            'tokens' => 'Placeholders:',
            'default' => 'Default:',
            'reset' => 'Reset to default',
            'saveTexts' => 'Save texts',
            'preview' => 'Preview with sample data',
            'previewTitle' => 'Mail preview',
            'textPart' => 'Text part',
            'test' => 'Send a test mail to me',
        ],
    ];

    /**
     * @param array<string, mixed>  $options
     * @param array<string, string> $posted
     *
     * @return array<string, mixed>
     */
    public static function apply(array $options, string $bundle, string $kind, string $locale, array $posted): array
    {
        if (!\in_array($kind, EditableMail::kinds($bundle), true) || !\in_array($locale, ['de', 'en'], true)) {
            throw new \InvalidArgumentException('Unknown mail type or language.');
        }
        $action = $posted['mailAction'] ?? 'save';
        if ('identity' === $action) {
            foreach (['senderName', 'senderAddress', 'replyTo', 'accent', 'logoPath', 'voice'] as $field) {
                $options[$field] = trim($posted[$field] ?? '');
            }
        } elseif ('reset' === $action) {
            $field = $posted['resetField'] ?? '';
            if (!\in_array($field, EditableMail::FIELDS, true)) {
                throw new \InvalidArgumentException('Unknown mail field.');
            }
            unset($options['templates'][$locale][$kind][$field]);
        } elseif ('save' === $action) {
            foreach (EditableMail::FIELDS as $field) {
                if (!\array_key_exists($field, $posted)) {
                    throw new \InvalidArgumentException('Incomplete mail form.');
                }
                $options['templates'][$locale][$kind][$field] = $posted[$field];
            }
        } else {
            throw new \InvalidArgumentException('Unknown mail action.');
        }

        return MailOptions::validate($options, $bundle);
    }

    /**
     * @return array<string, string>
     */
    public static function sampleTokens(string $kind): array
    {
        $examples = [
            'customer.name' => 'Ada Beispiel', 'order.number' => 'MS-000123',
            'booking.reference' => 'SEM-ABC123', 'event.title' => 'Keramikkurs',
            'event.date' => '15. Oktober 2026', 'contract.reference' => 'MS-000123',
        ];

        return array_intersect_key($examples, array_flip(EditableMail::tokens($kind)));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{subject: string, text: string, html: string}
     */
    public static function preview(MailDocument $document, array $options, string $kind, string $locale): array
    {
        $en = 'en' === $locale;
        $withdrawal = str_starts_with($kind, 'withdrawal_');
        $merchant = str_ends_with($kind, '_merchant');
        $sections = [[
            'title' => $en ? 'Fixed booking or contract details (sample)' : 'Feste Buchungs- oder Vertragsangaben (Beispiel)',
            'text' => $en
                ? "Reference: MS-000123\nReceived: 15 October 2026, 10:30\nProvider: Nordwerk, Sample Street 1, 12345 Berlin\nWithdrawal notice and form: version at contract conclusion"
                : "Referenz: MS-000123\nEingang: 15.10.2026, 10:30 Uhr\nAnbieter: Nordwerk, Musterstraße 1, 12345 Berlin\nWiderrufsbelehrung und Musterformular: Stand bei Vertragsschluss",
        ] + ($withdrawal || $merchant ? [] : ['style' => 'legal'])];
        $items = $withdrawal ? [] : [[
            'name' => $en ? 'Sample product or event' : 'Beispielprodukt oder Termin',
            'meta' => $en ? '100 g' : '100 g',
            'quantity' => '2', 'unit' => '25,00 €', 'amount' => '50,00 €',
        ]];
        $totals = [] === $items ? [] : [['label' => $en ? 'Total' : 'Gesamt', 'amount' => '50,00 €', 'strong' => true]];
        $facts = $withdrawal ? [] : [
            ['label' => $en ? 'Reference' : 'Referenz', 'value' => 'MS-000123'],
            ['label' => $en ? 'Date' : 'Datum', 'value' => $en ? '15/10/2026, 10:30' : '15.10.2026, 10:30 Uhr'],
        ];
        $footer = $withdrawal || $merchant ? '' : ($en ? 'Nordwerk · Sample Street 1 · 12345 Berlin' : 'Nordwerk · Musterstraße 1 · 12345 Berlin');

        return $document->render($kind, $locale, MailOptions::fields($options, $kind, $locale), self::sampleTokens($kind), $sections, (string) ($options['senderName'] ?: 'Nordwerk'), $items, $totals, (string) $options['accent'], logo: null !== MailOptions::logoPath($options), facts: $facts, footer: $footer);
    }

    /**
     * The editor as Contao backend markup (tl_formbody_edit, widgets, submit
     * container), in English when the backend runs in English.
     *
     * @param array<string, mixed>                               $options
     * @param array{subject: string, html: string, text: string} $preview
     */
    public static function html(array $options, string $bundle, string $kind, string $locale, string $action, string $requestToken, array $preview, string $notice = '', string $error = '', string|null $backUrl = null, bool $english = false): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $t = static fn (string $key): string => self::LABELS[$english ? 'en' : 'de'][$key];
        $fields = MailOptions::fields($options, $kind, $locale);
        $defaults = EditableMail::defaults($kind, $locale, (string) $options['voice']);
        $kinds = EditableMail::kinds($bundle);
        $url = $e($action).'&amp;mail='.$e($kind).'&amp;locale='.$e($locale);
        $token = '<input type="hidden" name="REQUEST_TOKEN" value="'.$e($requestToken).'">';
        $submit = static fn (string $buttons): string => '<div class="tl_formbody_submit"><div class="tl_submit_container">'.$buttons.'</div></div>';

        $out = '<div class="tl_listing_container mail-editor">';
        if ('' !== $notice) {
            $out .= '<p class="tl_confirm">'.$e($notice).'</p>';
        }
        if ('' !== $error) {
            $out .= '<p class="tl_error" role="alert">'.$e($error).'</p>';
        }
        $out .= '<p class="tl_help">'.$e($t('intro')).' <a href="'.$e($backUrl ?? $action).'">'.$e($t('back')).'</a></p>';
        $out .= '<nav class="mail-editor-kinds" aria-label="'.$e($t('kind')).'">';

        foreach ($kinds as $choice) {
            $current = $choice === $kind ? ' aria-current="page"' : '';
            $out .= '<a class="tl_submit"'.$current.' href="'.$e($action).'&amp;mail='.$e($choice).'&amp;locale='.$e($locale).'">'.$e(str_replace('_', ' ', $choice)).'</a> ';
        }
        $out .= '</nav><p class="mail-editor-locales"><a href="'.$e($action).'&amp;mail='.$e($kind).'&amp;locale=de"'.('de' === $locale ? ' aria-current="page"' : '').'>Deutsch</a> · <a href="'.$e($action).'&amp;mail='.$e($kind).'&amp;locale=en"'.('en' === $locale ? ' aria-current="page"' : '').'>English</a></p></div>';

        // Sender and appearance
        $out .= '<form class="tl_form tl_edit_form" method="post" action="'.$url.'"><div class="tl_formbody_edit">'.$token.'<fieldset class="tl_tbox"><legend>'.$e($t('identity')).'</legend><div class="widget-group">';

        foreach (['senderName', 'senderAddress', 'replyTo', 'accent', 'logoPath'] as $name) {
            $out .= '<div class="widget w50"><h3><label for="mail_'.$name.'">'.$e($t($name)).'</label></h3><input class="tl_text" id="mail_'.$name.'" name="'.$name.'" value="'.$e($options[$name]).'"><p class="tl_help tl_tip">'.$e($t($name.'Help')).'</p></div>';
        }
        $out .= '<div class="widget w50"><h3><label for="mail_voice">'.$e($t('voice')).'</label></h3><select class="tl_select" id="mail_voice" name="voice"><option value="sie"'.('sie' === $options['voice'] ? ' selected' : '').'>Sie</option><option value="du"'.('du' === $options['voice'] ? ' selected' : '').'>Du</option></select><p class="tl_help tl_tip">'.$e($t('voiceHelp')).'</p></div>';
        $out .= '</div></fieldset></div>'.$submit('<button class="tl_submit" name="mailAction" value="identity">'.$e($t('saveIdentity')).'</button>').'</form>';

        // Texts of the chosen mail
        $out .= '<form class="tl_form tl_edit_form" method="post" action="'.$url.'"><div class="tl_formbody_edit">'.$token.'<input type="hidden" name="mailAction" value="save"><fieldset class="tl_tbox"><legend>'.$e(str_replace('_', ' ', $kind)).' · '.$e($locale).'</legend><div class="widget-group">';

        foreach (EditableMail::FIELDS as $field) {
            $out .= '<div class="widget clr"><h3><label for="mail_'.$field.'">'.$e($t($field)).'</label></h3><textarea class="tl_textarea" style="height:auto" id="mail_'.$field.'" name="'.$field.'" rows="'.('introduction' === $field || 'closing' === $field ? 5 : 2).'">'.$e($fields[$field]).'</textarea>';
            $out .= '<p class="tl_help">'.$e($t('tokens')).' ';

            foreach (EditableMail::tokens($kind) as $placeholder) {
                $out .= '<button type="button" class="mail-token" data-target="mail_'.$field.'" data-token="'.$e('{{ '.$placeholder.' }}').'">'.$e('{{ '.$placeholder.' }}').'</button> ';
            }
            $out .= '<br>'.$e($t('default')).' '.$e($defaults[$field]).' <button class="mail-reset" name="resetField" value="'.$field.'" onclick="this.form.mailAction.value=\'reset\'">'.$e($t('reset')).'</button></p></div>';
        }
        $out .= '</div></fieldset></div>'.$submit('<button class="tl_submit" type="submit">'.$e($t('saveTexts')).'</button>').'</form>';

        // Preview and test mail
        $previewHtml = $preview['html'];
        if ('' !== (string) $options['logoPath']) {
            $previewHtml = str_replace('cid:nw-logo', '/'.str_replace('%2F', '/', rawurlencode((string) $options['logoPath'])), $previewHtml);
        }
        $out .= '<form class="tl_form tl_edit_form" method="post" action="'.$url.'"><div class="tl_formbody_edit">'.$token.'<fieldset class="tl_tbox"><legend>'.$e($t('preview')).'</legend><div class="widget clr"><p><strong>'.$e($preview['subject']).'</strong></p><iframe title="'.$e($t('previewTitle')).'" style="width:100%;height:650px;border:1px solid var(--border, #ddd);background:#fff" srcdoc="'.$e($previewHtml).'"></iframe><details><summary>'.$e($t('textPart')).'</summary><pre>'.$e($preview['text']).'</pre></details></div></fieldset></div>';
        $out .= $submit('<button class="tl_submit" name="mailAction" value="test">'.$e($t('test')).'</button>').'</form>';
        $out .= '<style>.mail-editor{padding:12px 18px}.mail-editor-kinds{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 6px}.mail-editor-kinds [aria-current]{font-weight:600}.mail-editor-locales [aria-current]{font-weight:600}.mail-editor a:not(.tl_submit){text-decoration:underline}.mail-token,.mail-reset{margin:2px 2px 0 0;padding:1px 6px;font:inherit;font-size:.9em;cursor:pointer}</style>';
        $out .= '<script>document.querySelectorAll(".mail-token").forEach(button=>button.addEventListener("click",()=>{const field=document.getElementById(button.dataset.target);const pos=field.selectionStart;field.setRangeText(button.dataset.token,pos,field.selectionEnd,"end");field.focus()}));</script>';

        return $out;
    }
}
