<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Command;

use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'withdrawal:resend-pending', description: 'Resend pending withdrawal emails')]
final class ResendPendingCommand extends Command
{
    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly WithdrawalMailer $mailer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = 0;
        $sent = 0;

        foreach ($this->repository->pending() as $row) {
            try {
                $this->mailer->sendPending($row);
                ++$sent;
            } catch (\Throwable $exception) {
                ++$failed;
                $output->writeln(\sprintf('<error>Withdrawal %d: %s</error>', $row['id'], $exception->getMessage()));
            }
        }

        $output->writeln(\sprintf('Processed: %d; failed: %d', $sent, $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
