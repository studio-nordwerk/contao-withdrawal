<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final readonly class MailDocument
{
    public function __construct(private Environment|null $twig = null)
    {
    }

    /**
     * Sections are shown as plain text blocks. A section with style "box" stands out
     * near the top (e.g. payment details, optionally with an embedded image);
     * sections with style "legal" form the small print at the end (withdrawal notice,
     * terms, ...).
     *
     * @param array<string, string>                                                                                                                  $fields
     * @param array<string, string>                                                                                                                  $tokens
     * @param list<array{title: string, text: string, style?: 'box'|'legal', image?: array{src: string, alt: string, width: int, caption?: string}}> $sections
     * @param list<array{name: string, quantity: string, unit: string, amount: string, meta?: string, url?: string, image?: string}>                 $items
     * @param list<array{label: string, amount: string, strong?: bool}>                                                                              $totals
     * @param list<array{label: string, value: string}>                                                                                              $facts
     * @param array{label: string, url: string}|null                                                                                                 $action
     *
     * @return array{subject: string, text: string, html: string}
     */
    public function render(string $kind, string $locale, array $fields, array $tokens, array $sections, string $brand, array $items = [], array $totals = [], string $accent = '#382f3d', bool $requireSections = true, bool $logo = false, array $facts = [], array|null $action = null, string $totalsNote = '', string $footer = ''): array
    {
        if ($requireSections && [] === $sections) {
            throw new \InvalidArgumentException('Fixed mail sections are required.');
        }

        foreach ($sections as $section) {
            if ('' === trim($section['title']) || '' === trim($section['text'])) {
                throw new \InvalidArgumentException('A fixed mail section is empty.');
            }
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            throw new \InvalidArgumentException('Invalid mail accent color.');
        }
        if (null !== $action && !preg_match('#^https?://#', $action['url'])) {
            throw new \InvalidArgumentException('A mail action needs an absolute URL.');
        }
        $resolved = [];

        foreach (EditableMail::FIELDS as $field) {
            $resolved[$field] = EditableMail::expand($kind, $field, $fields[$field] ?? '', $tokens);
        }
        $resolved['subject'] = EditableMail::header($resolved['subject']);
        $en = 'en' === $locale;
        $byStyle = static fn (string $style): array => array_values(array_filter($sections, static fn (array $section): bool => ($section['style'] ?? '') === $style));
        $boxes = $byStyle('box');
        $plain = $byStyle('');
        $legal = $byStyle('legal');
        $sectionText = static fn (array $section): string => $section['title']."\n".$section['text'];
        $itemLines = [];

        foreach ($items as $item) {
            $itemLines[] = $item['name'].('' !== ($item['meta'] ?? '') ? ' ('.$item['meta'].')' : '').' | '.$item['quantity'].' × '.$item['unit'].' | '.$item['amount'];
        }

        foreach ($totals as $total) {
            $itemLines[] = $total['label'].': '.$total['amount'];
        }
        if ('' !== $totalsNote) {
            $itemLines[] = $totalsNote;
        }
        $lines = array_filter(
            [
                $resolved['introduction'],
                null !== $action ? $action['label'].': '.$action['url'] : '',
                ...array_map($sectionText, $boxes),
                implode("\n", $itemLines),
                implode("\n", array_map(static fn (array $fact): string => $fact['label'].': '.str_replace("\n", ', ', $fact['value']), $facts)),
                ...array_map($sectionText, $plain),
                $resolved['closing'],
                trim($resolved['greeting']."\n".$resolved['signature']),
                [] !== $legal ? '— '.($en ? 'Contract documents' : 'Ihre Vertragsunterlagen').' —' : '',
                ...array_map($sectionText, $legal),
                $footer,
            ],
            static fn (string $line): bool => '' !== trim($line),
        );
        $text = implode("\n\n", $lines)."\n";
        $twig = $this->twig;
        if (null === $twig) {
            $loader = new FilesystemLoader();
            $loader->addPath(\dirname(__DIR__, 2).'/templates', 'NordwerkWithdrawal');
            $twig = new Environment($loader);
        }
        $html = $twig->render('@NordwerkWithdrawal/mail/document.html.twig', [
            ...$resolved,
            'brand' => $brand,
            'sections' => $plain,
            'boxes' => $boxes,
            'legal' => $legal,
            'items' => $items,
            'thumbnails' => [] !== array_filter($items, static fn (array $item): bool => '' !== ($item['image'] ?? '')),
            'totals' => $totals,
            'totals_note' => $totalsNote,
            'facts' => $facts,
            'action' => $action,
            'footer' => $footer,
            'locale' => $locale,
            'accent' => $accent,
            'accent_text' => self::readableOn($accent),
            'logo' => $logo,
        ]);
        $html = (new CssToInlineStyles())->convert($html);

        return ['subject' => $resolved['subject'], 'text' => $text, 'html' => $html];
    }

    /**
     * White or near-black text on the accent colour, whichever has more contrast.
     */
    private static function readableOn(string $hex): string
    {
        $luminance = 0.0;

        foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
            $channel = hexdec(substr($hex, 1 + 2 * $index, 2)) / 255;
            $luminance += $weight * ($channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4);
        }

        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.0625 ? '#ffffff' : '#1d1a1f';
    }
}
