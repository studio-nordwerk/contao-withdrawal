<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;
use PHPUnit\Framework\TestCase;

final class WithdrawalDeclarationTest extends TestCase
{
    public function testOnlyThreeRequiredFieldsAreValidated(): void
    {
        $this->assertSame([], WithdrawalDeclaration::validate('Ada Example', 'ORDER-123', 'ada@example.test'));
        $this->assertSame(['name' => 'name', 'contractReference' => 'contractReference', 'email' => 'email'], WithdrawalDeclaration::validate('', '', 'not-an-email'));
    }

    public function testExcessivelyLongFieldsAreRejected(): void
    {
        $this->assertArrayHasKey('contractReference', WithdrawalDeclaration::validate('Ada', str_repeat('x', 2001), 'ada@example.test'));
    }
}
