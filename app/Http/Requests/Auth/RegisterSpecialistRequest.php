<?php

namespace App\Http\Requests\Auth;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Rules\ValidNip;

/**
 * Rejestracja fachowca: dodatkowo nazwa firmy, NIP (format + suma kontrolna, bez zapytań do rejestrów)
 * i oświadczenie o prowadzeniu działalności. Zajęty NIP NIE blokuje rejestracji (SPECYFIKACJA.md §10).
 */
class RegisterSpecialistRequest extends RegisterRequest
{
    /**
     * Rola nadawana przy rejestracji.
     */
    public function role(): UserRole
    {
        return UserRole::Specialist;
    }

    /**
     * Reguły wspólne plus pola fachowca.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'company_name' => ['required', 'string', 'max:190'],
            'nip' => ['required', 'string', new ValidNip],
            'business_declaration' => ['accepted'],
        ];
    }
}
