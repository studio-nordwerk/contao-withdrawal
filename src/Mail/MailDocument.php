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
     * @param array<string, string>                                                     $fields
     * @param array<string, string>                                                     $tokens
     * @param list<array{title: string, text: string}>                                  $sections
     * @param list<array{name: string, quantity: string, unit: string, amount: string}> $items
     * @param list<array{label: string, amount: string}>                                $totals
     *
     * @return array{subject: string, text: string, html: string}
     */
    public function render(string $kind, string $locale, array $fields, array $tokens, array $sections, string $brand, array $items = [], array $totals = [], string $accent = '#382f3d', bool $requireSections = true, bool $logo = false): array
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
        $resolved = [];

        foreach (EditableMail::FIELDS as $field) {
            $resolved[$field] = EditableMail::expand($kind, $field, $fields[$field] ?? '', $tokens);
        }
        $resolved['subject'] = EditableMail::header($resolved['subject']);
        $itemLines = [];

        foreach ($items as $item) {
            $itemLines[] = $item['name'].' | '.$item['quantity'].' × '.$item['unit'].' | '.$item['amount'];
        }

        foreach ($totals as $total) {
            $itemLines[] = $total['label'].': '.$total['amount'];
        }
        $lines = array_filter(
            [
                $resolved['introduction'],
                implode("\n", $itemLines),
                ...array_map(static fn (array $section): string => $section['title']."\n".$section['text'], $sections),
                $resolved['closing'],
                trim($resolved['greeting']."\n".$resolved['signature']),
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
            'sections' => $sections,
            'items' => $items,
            'totals' => $totals,
            'locale' => $locale,
            'accent' => $accent,
            'logo' => $logo,
        ]);
        $html = (new CssToInlineStyles())->convert($html);

        return ['subject' => $resolved['subject'], 'text' => $text, 'html' => $html];
    }
}
