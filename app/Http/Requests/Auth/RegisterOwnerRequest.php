<?php

namespace App\Http\Requests\Auth;

use App\Domain\Accounts\Enums\UserRole;

/**
 * Rejestracja właściciela lub zarządcy nieruchomości.
 */
class RegisterOwnerRequest extends RegisterRequest
{
    /**
     * Rola nadawana przy rejestracji.
     */
    public function role(): UserRole
    {
        return UserRole::Owner;
    }
}
