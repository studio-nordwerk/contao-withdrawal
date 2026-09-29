<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Domain;

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

        if ('' === trim($name) || mb_strlen($name) > 255) {
            $errors['name'] = 'name';
        }

        if ('' === trim($contractReference) || mb_strlen($contractReference) > 2000) {
            $errors['contractReference'] = 'contractReference';
        }

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors['email'] = 'email';
        }

        return $errors;
    }
}
