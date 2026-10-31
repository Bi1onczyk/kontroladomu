<?php

namespace App\Domain\Specialists\Policies;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;

/**
 * Uprawnienia do profilu fachowca.
 *
 * Edytować i publikować może tylko właściciel (rola `specialist`, konto niezablokowane).
 * Oglądać: każdy, jeśli profil jest publiczny; niepubliczny tylko właściciel i administrator (podgląd).
 */
class SpecialistProfilePolicy
{
    /**
     * Czy użytkownik (także gość) może zobaczyć stronę profilu.
     */
    public function view(?User $user, SpecialistProfile $profile): bool
    {
        if ($profile->isPubliclyVisible()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->id === $profile->user_id || $user->hasRole(UserRole::Admin->value);
    }

    /**
     * Czy użytkownik może edytować profil (tylko właściciel z rolą fachowca, niezablokowany).
     */
    public function update(User $user, SpecialistProfile $profile): bool
    {
        return $user->id === $profile->user_id
            && $user->hasRole(UserRole::Specialist->value)
            && ! $user->isBanned();
    }

    /**
     * Czy użytkownik może opublikować profil: jak edycja, a profil nie może mieć odrzuconej ani cofniętej weryfikacji.
     *
     * Kompletność profilu (`ProfileCompleteness`) sprawdza akcja `PublishSpecialistProfile` i zgłasza ją jako błąd
     * walidacji z listą braków (a nie odmowę 403), dlatego nie jest warunkiem tej metody.
     */
    public function publish(User $user, SpecialistProfile $profile): bool
    {
        return $this->update($user, $profile) && $profile->verification_status->isPubliclyVisible();
    }
}
