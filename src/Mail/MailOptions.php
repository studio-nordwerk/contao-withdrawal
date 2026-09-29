<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Shared storage shape for the three bundles; easy to move into commerce later. */
final class MailOptions
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'senderName' => '', 'senderAddress' => '', 'replyTo' => '',
            'accent' => '#382f3d', 'logoPath' => '', 'voice' => 'sie', 'templates' => [],
        ];
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    public static function validate(array $values, string $bundle): array
    {
        $options = array_replace(self::defaults(), $values);
        foreach (['senderName', 'senderAddress', 'replyTo', 'accent', 'logoPath', 'voice'] as $name) {
            if (!\is_string($options[$name])) {
                throw new \InvalidArgumentException('Invalid mail setting: '.$name);
            }
        }
        EditableMail::header($options['senderName']);
        foreach (['senderAddress', 'replyTo'] as $key) {
            if ('' !== $options[$key] && (false === filter_var($options[$key], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $options[$key]))) {
                throw new \InvalidArgumentException('Invalid mail address: '.$key);
            }
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $options['accent']) || !\in_array($options['voice'], ['sie', 'du'], true)) {
            throw new \InvalidArgumentException('Invalid mail design or salutation.');
        }
        if ('' !== $options['logoPath'] && (!str_starts_with($options['logoPath'], 'files/') || str_contains($options['logoPath'], '..') || preg_match('/[\r\n]/', $options['logoPath']))) {
            throw new \InvalidArgumentException('The mail logo must be below files/.');
        }
        if (!\is_array($options['templates'])) {
            throw new \InvalidArgumentException('Invalid mail templates.');
        }
        foreach ($options['templates'] as $locale => $kinds) {
            if (!\in_array($locale, ['de', 'en'], true) || !\is_array($kinds)) {
                throw new \InvalidArgumentException('Unknown mail language.');
            }
            foreach ($kinds as $kind => $fields) {
                if (!\in_array($kind, EditableMail::kinds($bundle), true) || !\is_array($fields)) {
                    throw new \InvalidArgumentException('Unknown mail type.');
                }
                EditableMail::resolve($kind, $locale, $options['voice'], $fields);
            }
        }

        return $options;
    }

    /** @param array<string, mixed> $options
     *  @return array<string, string>
     */
    public static function fields(array $options, string $kind, string $locale): array
    {
        return EditableMail::resolve($kind, $locale, (string) ($options['voice'] ?? 'sie'), $options['templates'][$locale][$kind] ?? []);
    }

    /** @param array<string, mixed> $options */
    public static function address(Email $email, array $options, string $fallbackAddress, string $fallbackName): Email
    {
        $address = (string) ($options['senderAddress'] ?: $fallbackAddress);
        $name = (string) ($options['senderName'] ?: $fallbackName);
        EditableMail::header($name);
        if (false === filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $address)) {
            throw new \InvalidArgumentException('Invalid mail sender.');
        }
        $email->from(new Address($address, $name));
        if ('' !== ($options['replyTo'] ?? '')) {
            $email->replyTo((string) $options['replyTo']);
        }

        return $email;
    }

    /** @param array<string, mixed> $options */
    public static function logoPath(array $options): string|null
    {
        $relative = (string) ($options['logoPath'] ?? '');
        if ('' === $relative) {
            return null;
        }
        $public = $_SERVER['DOCUMENT_ROOT'] ?? getcwd().'/public';
        $root = realpath($public.'/files');
        $path = realpath($public.'/'.$relative);
        if (false === $root || false === $path || !str_starts_with($path, $root.'/') || !\in_array((string) mime_content_type($path), ['image/png', 'image/jpeg', 'image/gif'], true)) {
            throw new \InvalidArgumentException('Mail logo must be a PNG, JPEG or GIF below public/files/.');
        }

        return $path;
    }

    /** @param array<string, mixed> $options */
    public static function embedLogo(Email $email, array $options): Email
    {
        $path = self::logoPath($options);
        if (null !== $path) {
            $email->embedFromPath($path, 'nw-logo');
        }

        return $email;
    }
}
