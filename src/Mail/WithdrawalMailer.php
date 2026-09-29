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
        $language = $english ? '.en' : '';
        $merchantEmail = $this->settings?->merchantEmail() ?? $this->merchantEmail;

        $failure = null;

        foreach ([
            ['confirmationSentAt', $data['email'], $english ? 'Confirmation of your withdrawal' : 'Eingangsbestätigung Ihres Widerrufs', 'consumer'],
            ['merchantSentAt', $merchantEmail, $english ? 'New withdrawal' : 'Neuer Widerruf', 'merchant'],
        ] as [$column, $recipient, $subject, $template]) {
            if (null !== $row[$column]) {
                continue;
            }

            try {
                $body = $this->twig->render('@NordwerkWithdrawal/mail/'.$template.$language.'.txt.twig', $data);
                $sent = $this->transport->send((new Email())
                    ->from($merchantEmail)
                    ->to($recipient)
                    ->subject($subject)
                    ->text($body)
                    ->html($this->twig->render('@NordwerkWithdrawal/mail/'.$template.$language.'.html.twig', [
                        ...$data, 'body' => $body, 'subject' => $subject, 'merchant' => $merchantEmail, 'locale' => $english ? 'en' : 'de',
                    ])));
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
