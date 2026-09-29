<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Event;

use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;

final readonly class WithdrawalSubmittedEvent
{
    public function __construct(
        public int $id,
        public WithdrawalDeclaration $declaration,
        public \DateTimeImmutable $submittedAt,
    ) {
    }
}
