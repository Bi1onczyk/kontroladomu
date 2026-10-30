<?php

namespace App\Domain\Accounts\Data;

use App\Domain\Accounts\Enums\UserRole;

/**
 * Dane z formularza rejestracji, już zwalidowane i znormalizowane (e-mail małymi literami, NIP same cyfry).
 */
final readonly class RegisterAccountData
{
    public function __construct(
        public UserRole $role,
        public string $name,
        public string $email,
        public string $password,
        public ?string $companyName,
        public ?string $nip,
        public bool $marketingConsent,
    ) {}
}
