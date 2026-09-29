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
        $data = [
            'name' => (string) $row['consumerName'],
            'contractReference' => (string) $row['contractReference'],
            'email' => (string) $row['email'],
            'submittedAt' => (new \DateTimeImmutable((string) $row['submittedAt'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Berlin')),
        ];
        $english = 'en' === $row['locale'];
        $language = $english ? '.en' : '';

        if (null === $row['confirmationSentAt']) {
            $this->mailer->send((new Email())
                ->from($this->merchantEmail)
                ->to($data['email'])
                ->subject($english ? 'Confirmation of your withdrawal' : 'Eingangsbestätigung Ihres Widerrufs')
                ->text($this->twig->render('@NordwerkWithdrawal/mail/consumer'.$language.'.txt.twig', $data)));
            $this->repository->markSent((int) $row['id'], 'confirmationSentAt', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        }

        if (null === $row['merchantSentAt']) {
            $this->mailer->send((new Email())
                ->from($this->merchantEmail)
                ->to($this->merchantEmail)
                ->subject($english ? 'New withdrawal' : 'Neuer Widerruf')
                ->text($this->twig->render('@NordwerkWithdrawal/mail/merchant'.$language.'.txt.twig', $data)));
            $this->repository->markSent((int) $row['id'], 'merchantSentAt', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        }
    }
}
