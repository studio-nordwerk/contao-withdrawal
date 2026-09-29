<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Domain;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;

final readonly class WithdrawalDeclaration
{
    public function __construct(
        public string $name,
        public string $contractReference,
        public string $email,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function validate(string $name, string $contractReference, string $email): array
    {
        $errors = [];

        if (!self::isText($name, 255) || 1 === preg_match('/[\r\n\t]/', $name)) {
            $errors['name'] = 'name';
        }

        if (!self::isText($contractReference, 2000)) {
            $errors['contractReference'] = 'contractReference';
        }

        if (!self::isText($email, 255) || 1 === preg_match('/[\r\n\t]/', $email) || !(new EmailValidator())->isValid($email, new RFCValidation())) {
            $errors['email'] = 'email';
        }

        return $errors;
    }

    private static function isText(string $value, int $maxLength): bool
    {
        return mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value) <= $maxLength
            && 1 === preg_match('/[^\p{Z}\p{C}]/u', $value)
            && 0 === preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x9f]/u', $value);
    }
}
