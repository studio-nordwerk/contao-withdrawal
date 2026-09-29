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

    public function testUnicodeAddressesAndNamesAreAcceptedWithoutDnsLookup(): void
    {
        $this->assertSame([], WithdrawalDeclaration::validate('李 Müller', 'Teil: Größe 🧥', 'kunde@bücher.example'));
        $this->assertSame([], WithdrawalDeclaration::validate('李 Müller', 'Teil: Größe 🧥', '用户@example.test'));
    }

    public function testInvisibleEmptyInputAndControlCharactersAreRejected(): void
    {
        $this->assertArrayHasKey('name', WithdrawalDeclaration::validate("\u{00a0}\u{200b}", 'Order', 'a@example.test'));
        $this->assertArrayHasKey('contractReference', WithdrawalDeclaration::validate('Ada', "Order\0hidden", 'a@example.test'));
        $this->assertArrayHasKey('name', WithdrawalDeclaration::validate("Ada\r\nBcc: victim@example.test", 'Order', 'a@example.test'));
        $this->assertArrayHasKey('email', WithdrawalDeclaration::validate('Ada', 'Order', "a@example.test\r\nBcc: victim@example.test"));
        $this->assertArrayHasKey('name', WithdrawalDeclaration::validate("\xff", 'Order', 'a@example.test'));
        $this->assertSame([], WithdrawalDeclaration::validate('Ada', "Order\r\nLine 2\tpart", 'a@example.test'));
    }
}
