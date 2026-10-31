<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Enums\AccountType;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Zwraca profil fachowca, a gdy go jeszcze nie ma, zakłada pusty (idempotentnie).
 *
 * Profil powstaje przy pierwszym wejściu do panelu (decyzja świadoma: brak osobnego kroku "załóż profil").
 * Tylko dla kont z rolą `specialist`; dla innych zwraca null. Startowa nazwa publiczna to nazwa firmy z rejestracji
 * albo imię i nazwisko, startowy adres to slug z tej nazwy. Równoczesne żądania rozstrzyga indeks UNIQUE:
 * po konflikcie akcja wczytuje istniejący profil albo ponawia próbę z nowym sufiksem sluga.
 */
class EnsureSpecialistProfile
{
    /** Ile razy ponawiamy próbę po konflikcie sluga. */
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly GenerateSpecialistSlug $slugs) {}

    /**
     * Zwraca (i w razie potrzeby tworzy) profil użytkownika; null, gdy konto nie ma roli fachowca.
     */
    public function handle(User $user): ?SpecialistProfile
    {
        if (! $user->hasRole(UserRole::Specialist->value)) {
            return null;
        }

        $existing = SpecialistProfile::query()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(fn (): SpecialistProfile => $this->create($user));
            } catch (UniqueConstraintViolationException $e) {
                // Konflikt na user_id: profil założyło równoległe żądanie. Konflikt na slug: próbujemy ponownie.
                $existing = SpecialistProfile::query()->where('user_id', $user->id)->first();

                if ($existing !== null) {
                    return $existing;
                }

                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        // Nieosiągalne (ostatnia próba zwraca wynik albo rzuca wyjątek); dla analizy statycznej.
        throw new \LogicException('Nie udało się założyć profilu fachowca.');
    }

    /**
     * Tworzy pusty profil z domyślnymi wartościami.
     */
    private function create(User $user): SpecialistProfile
    {
        $displayName = trim((string) ($user->company_name ?: $user->name));
        $displayName = mb_substr($displayName, 0, (int) config('specialists.display_name_max_length'));

        $profile = new SpecialistProfile;
        $profile->fill([
            'display_name' => $displayName,
            'account_type' => AccountType::SoleProprietor,
            'service_radius_km' => (int) config('specialists.radius_default_km'),
            'travel_rate_per_km_grosze' => 0,
            'travel_free_km' => 0,
        ]);
        $profile->forceFill([
            'user_id' => $user->id,
            'slug' => $this->slugs->handle($displayName),
            'location_status' => LocationStatus::Pending,
            'verification_status' => VerificationStatus::Pending,
            'is_published' => false,
        ])->save();

        return $profile;
    }
}
