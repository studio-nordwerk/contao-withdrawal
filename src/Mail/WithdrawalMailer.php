<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use Nordwerk\WithdrawalBundle\Settings\WithdrawalSettings;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class WithdrawalMailer
{
    public function __construct(
        private TransportInterface $transport,
        private Environment $twig,
        private WithdrawalRepository $repository,
        private string $merchantEmail,
        private WithdrawalSettings|null $settings = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function sendPending(array $row): void
    {
        $this->repository->withMailLock((int) $row['id'], $this->deliver(...));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendTest(string $kind, string $locale, string $recipient, array $options): void
    {
        if (!\in_array($kind, EditableMail::kinds('withdrawal'), true) || false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid test mail.');
        }
        $content = MailEditor::preview(new MailDocument($this->twig), $options, $kind, $locale);
        $sender = $this->settings?->merchantEmail() ?? $this->merchantEmail;
        $message = MailOptions::address(new Email(), $options, $sender, $sender)
            ->to($recipient)->subject('[Test] '.$content['subject'])->text($content['text'])->html($content['html'])
        ;
        MailOptions::embedLogo($message, $options);
        $this->transport->send($message);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function deliver(array $row): void
    {
        $data = [
            'name' => (string) $row['consumerName'],
            'contractReference' => (string) $row['contractReference'],
            'email' => (string) $row['email'],
            'submittedAt' => (new \DateTimeImmutable((string) $row['submittedAt'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Berlin')),
        ];
        $english = 'en' === $row['locale'];
        $locale = $english ? 'en' : 'de';
        $merchantEmail = $this->settings?->merchantEmail() ?? $this->merchantEmail;
        $options = $this->settings?->mailOptions() ?? MailOptions::defaults();
        $document = new MailDocument($this->twig);
        $received = $data['submittedAt']->format($english ? 'Y-m-d H:i:s' : 'd.m.Y H:i:s').($english ? '' : ' Uhr').' (Europe/Berlin) UTC'.$data['submittedAt']->format('P');

        $failure = null;

        foreach ([
            ['confirmationSentAt', $data['email'], 'withdrawal_consumer'],
            ['merchantSentAt', $merchantEmail, 'withdrawal_merchant'],
        ] as [$column, $recipient, $kind]) {
            if (null !== $row[$column]) {
                continue;
            }

            try {
                $declaration = $english
                    ? 'I hereby withdraw from the specified contract or part of the contract.'
                    : 'Hiermit widerrufe ich den bezeichneten Vertrag oder Vertragsteil.';
                $sections = [[
                    'title' => $english ? 'Withdrawal received' : 'Eingegangener Widerruf',
                    'text' => ($english ? 'Name: ' : 'Name: ').$data['name']."\n"
                        .($english ? 'Contract details: ' : 'Vertragsangaben: ').$data['contractReference']."\n"
                        .($english ? 'Email: ' : 'E-Mail: ').$data['email']."\n"
                        .($english ? 'Received: ' : 'Eingang: ').$received."\n"
                        .($english ? 'Declaration: ' : 'Erklärung: ').$declaration,
                ]];
                $tokens = ['customer.name' => $data['name'], 'contract.reference' => $data['contractReference']];
                $content = $document->render(
                    kind: $kind, locale: $locale, fields: MailOptions::fields($options, $kind, $locale),
                    tokens: $tokens, sections: $sections, brand: (string) ($options['senderName'] ?: $merchantEmail),
                    accent: (string) $options['accent'], logo: null !== MailOptions::logoPath($options),
                );

                $message = MailOptions::address(new Email(), $options, $merchantEmail, $merchantEmail)
                    ->to($recipient)
                    ->subject($content['subject'])
                    ->text($content['text'])
                    ->html($content['html'])
                ;
                MailOptions::embedLogo($message, $options);
                $sent = $this->transport->send($message);
                if (null === $sent) {
                    throw new \RuntimeException('Mail transport rejected the message.');
                }
                $this->repository->markSent((int) $row['id'], $column, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            } catch (\Throwable $exception) {
                $failure ??= $exception;
            }
        }

        if (null !== $failure) {
            throw $failure;
        }
    }
}
