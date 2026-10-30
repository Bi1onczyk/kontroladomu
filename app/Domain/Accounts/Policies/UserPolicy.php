<?php

namespace App\Domain\Accounts\Policies;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;

/**
 * Uprawnienia do operacji na kontach użytkowników (bezpieczeństwo konta, 2FA).
 */
class UserPolicy
{
    /**
     * Czy zalogowany może zarządzać ustawieniami bezpieczeństwa danego konta (tylko własne konto).
     */
    public function manageSecurity(User $actor, User $target): bool
    {
        return $actor->is($target);
    }

    /**
     * Czy można wyłączyć 2FA: tylko na własnym koncie i tylko gdy rola go nie wymaga.
     */
    public function disableTwoFactor(User $actor, User $target): bool
    {
        return $actor->is($target) && ! $target->requiresTwoFactor();
    }

    /**
     * Czy można zresetować 2FA innemu użytkownikowi: wyłącznie administrator.
     * (Komenda artisan `user:reset-2fa` działa poza Policy; to na przyszły panel admina.)
     */
    public function resetTwoFactor(User $actor, User $target): bool
    {
        return $actor->hasRole(UserRole::Admin->value);
    }
}
