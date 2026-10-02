<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

/**
 * Small Contao-independent editor shared by the three settings screens.
 */
final class MailEditor
{
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
     * @param array<string, mixed>                               $options
     * @param array{subject: string, text: string, html: string} $preview
     */
    public static function html(array $options, string $bundle, string $kind, string $locale, string $action, string $requestToken, array $preview, string $notice = '', string $error = ''): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $fields = MailOptions::fields($options, $kind, $locale);
        $defaults = EditableMail::defaults($kind, $locale, (string) $options['voice']);
        $kinds = EditableMail::kinds($bundle);
        $labels = ['subject' => 'Betreff', 'preheader' => 'Vorschautext', 'introduction' => 'Anrede und Einleitung', 'closing' => 'Schlusstext', 'greeting' => 'Grußformel', 'signature' => 'Signatur'];
        $out = '<h2 class="sub_headline">Mailtexte</h2><p>Pflegbare Texte; feste Pflichtangaben werden beim Versand aus dem Snapshot ergänzt.</p>';
        if ('' !== $notice) {
            $out .= '<p class="tl_confirm">'.$e($notice).'</p>';
        }
        if ('' !== $error) {
            $out .= '<p class="tl_error" role="alert">'.$e($error).'</p>';
        }
        $out .= '<p><a href="'.$e($action).'">Zurück zu den Einstellungen</a></p><nav aria-label="Mailtyp">';

        foreach ($kinds as $choice) {
            $out .= '<a style="display:inline-block;padding:6px" href="'.$e($action).'&amp;mail='.$e($choice).'&amp;locale='.$e($locale).'">'.$e(str_replace('_', ' ', $choice)).'</a> ';
        }
        $out .= '</nav><p><a href="'.$e($action).'&amp;mail='.$e($kind).'&amp;locale=de">Deutsch</a> · <a href="'.$e($action).'&amp;mail='.$e($kind).'&amp;locale=en">English</a></p>';
        $url = $action.'&amp;mail='.$e($kind).'&amp;locale='.$e($locale);
        $out .= '<form class="tl_form tl_edit_form" method="post" action="'.$url.'"><input type="hidden" name="REQUEST_TOKEN" value="'.$e($requestToken).'"><fieldset class="tl_tbox"><legend>Absender und Darstellung</legend>';

        foreach (['senderName' => 'Absendername', 'senderAddress' => 'Absenderadresse', 'replyTo' => 'Antwort an', 'accent' => 'Akzentfarbe (#RRGGBB)', 'logoPath' => 'Logo unter files/'] as $name => $label) {
            $out .= '<div class="widget"><label for="mail_'.$e($name).'">'.$label.'</label><input class="tl_text" id="mail_'.$e($name).'" name="'.$e($name).'" value="'.$e($options[$name]).'"></div>';
        }
        $out .= '<div class="widget"><label for="mail_voice">Anrede</label><select id="mail_voice" name="voice"><option value="sie"'.('sie' === $options['voice'] ? ' selected' : '').'>Sie</option><option value="du"'.('du' === $options['voice'] ? ' selected' : '').'>Du</option></select></div><button class="tl_submit" name="mailAction" value="identity">Absender speichern</button></fieldset></form>';
        $out .= '<form class="tl_form tl_edit_form" method="post" action="'.$url.'"><input type="hidden" name="REQUEST_TOKEN" value="'.$e($requestToken).'"><fieldset class="tl_tbox"><legend>'.$e($kind).' · '.$e($locale).'</legend>';

        foreach (EditableMail::FIELDS as $field) {
            $out .= '<div class="widget" style="margin-bottom:18px"><label for="mail_'.$field.'"><strong>'.$labels[$field].'</strong></label><textarea class="tl_textarea" id="mail_'.$field.'" name="'.$field.'" rows="'.('introduction' === $field || 'closing' === $field ? 5 : 2).'">'.$e($fields[$field]).'</textarea><div>Platzhalter: ';

            foreach (EditableMail::tokens($kind) as $token) {
                $out .= '<button type="button" class="mail-token" data-target="mail_'.$field.'" data-token="'.$e('{{ '.$token.' }}').'">'.$e('{{ '.$token.' }}').'</button> ';
            }
            $out .= '</div><small>Standard: '.$e($defaults[$field]).'</small><div><button class="tl_submit" name="resetField" value="'.$field.'" formaction="'.$url.'" onclick="this.form.mailAction.value=\'reset\'">Auf Standard zurücksetzen</button></div></div>';
        }
        $out .= '<input type="hidden" name="mailAction" value="save"><button class="tl_submit" type="submit">Texte speichern</button></fieldset></form>';
        $previewHtml = $preview['html'];
        if ('' !== (string) $options['logoPath']) {
            $previewHtml = str_replace('cid:nw-logo', '/'.str_replace('%2F', '/', rawurlencode((string) $options['logoPath'])), $previewHtml);
        }
        $out .= '<h3>Vorschau mit Beispieldaten</h3><p><strong>'.$e($preview['subject']).'</strong></p><iframe title="Mailvorschau" style="width:100%;height:650px;border:1px solid #ddd" srcdoc="'.$e($previewHtml).'"></iframe><details><summary>Textteil</summary><pre>'.$e($preview['text']).'</pre></details>';
        $out .= '<form method="post" action="'.$url.'"><input type="hidden" name="REQUEST_TOKEN" value="'.$e($requestToken).'"><button class="tl_submit" name="mailAction" value="test">Testmail an mich senden</button></form>';
        $out .= '<script>document.querySelectorAll(".mail-token").forEach(button=>button.addEventListener("click",()=>{const field=document.getElementById(button.dataset.target);const pos=field.selectionStart;field.setRangeText(button.dataset.token,pos,field.selectionEnd,"end");field.focus()}));</script>';

        return $out;
    }
}
