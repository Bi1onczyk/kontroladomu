<?php

namespace App\Domain\Specialists\Policies;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Models\User;

/**
 * Uprawnienia do zaświadczeń fachowca. Skan widzą wyłącznie właściciel profilu i administrator;
 * nie ma publicznego dostępu, a inni fachowcy, klienci i pracownicy (`technician`) nie mają żadnego.
 */
class SpecialistCertificatePolicy
{
    /**
     * Czy użytkownik może wejść na listę własnych zaświadczeń (rola fachowca).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Specialist->value);
    }

    /**
     * Czy użytkownik może dodać zaświadczenie. Limit liczby sprawdza akcja (czytelny komunikat zamiast 403).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Specialist->value) && ! $user->isBanned();
    }

    /**
     * Czy użytkownik może pobrać plik: właściciel profilu albo administrator.
     */
    public function view(User $user, SpecialistCertificate $certificate): bool
    {
        return $this->owns($user, $certificate) || $user->hasRole(UserRole::Admin->value);
    }

    /**
     * Czy użytkownik może zmienić dane zaświadczenia (tylko właściciel).
     */
    public function update(User $user, SpecialistCertificate $certificate): bool
    {
        return $this->owns($user, $certificate) && ! $user->isBanned();
    }

    /**
     * Czy użytkownik może usunąć zaświadczenie (tylko właściciel).
     */
    public function delete(User $user, SpecialistCertificate $certificate): bool
    {
        return $this->owns($user, $certificate) && ! $user->isBanned();
    }

    /**
     * Właściciel = konto z rolą fachowca, do którego należy profil zaświadczenia.
     */
    private function owns(User $user, SpecialistCertificate $certificate): bool
    {
        return $user->hasRole(UserRole::Specialist->value)
            && $certificate->profile->user_id === $user->id;
    }
}
