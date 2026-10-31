<?php

namespace App\Domain\Specialists\Policies;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Models\User;

/**
 * Uprawnienia do archiwum skanów po usunięciu konta (ADR-021): wyłącznie administrator.
 * W zadaniu 004 używana w testach; trasy panelu admina powstaną w zadaniu 005.
 */
class ArchivedCertificatePolicy
{
    /**
     * Czy użytkownik może przeglądać listę archiwum.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin->value);
    }

    /**
     * Czy użytkownik może pobrać zarchiwizowany skan.
     */
    public function view(User $user, ArchivedCertificate $certificate): bool
    {
        return $user->hasRole(UserRole::Admin->value);
    }

    /**
     * Czy użytkownik może usunąć wpis przed końcem okresu przechowywania (np. po uwzględnieniu sprzeciwu).
     */
    public function delete(User $user, ArchivedCertificate $certificate): bool
    {
        return $user->hasRole(UserRole::Admin->value);
    }
}
