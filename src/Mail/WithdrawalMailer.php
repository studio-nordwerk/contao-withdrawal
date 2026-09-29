<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Mail;

use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class WithdrawalMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private WithdrawalRepository $repository,
        private string $merchantEmail,
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

        $failure = null;

        foreach ([
            ['confirmationSentAt', $data['email'], $english ? 'Confirmation of your withdrawal' : 'Eingangsbestätigung Ihres Widerrufs', 'consumer'],
            ['merchantSentAt', $this->merchantEmail, $english ? 'New withdrawal' : 'Neuer Widerruf', 'merchant'],
        ] as [$column, $recipient, $subject, $template]) {
            if (null !== $row[$column]) {
                continue;
            }

            try {
                $this->mailer->send((new Email())
                    ->from($this->merchantEmail)
                    ->to($recipient)
                    ->subject($subject)
                    ->text($this->twig->render('@NordwerkWithdrawal/mail/'.$template.$language.'.txt.twig', $data)));
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
