<?php

namespace App\Domain\Specialists\Support;

use App\Domain\Specialists\Models\SpecialistProfile;

/**
 * Kompletność profilu: co jest wymagane do publikacji, a co tylko zalecane (ADR-021, S1, S10).
 *
 * Wymagane (klucze zgodne z `lang/pl/specialist.php` `steps.*`):
 * - `data`: nazwa publiczna oraz prywatny telefon (telefon przełączany `specialists.phone_required_to_publish`),
 * - `services`: co najmniej jedna pozycja cennika,
 * - `area`: wybrana miejscowość i poprawny promień (stan geokodowania NIE blokuje publikacji).
 * Zalecane (nigdy nie blokują): `description`, `certificates`.
 *
 * Konto zablokowane sprawdza Policy, a nie ta klasa. Klasa nie wykonuje zapytań, jeśli relacje `services`
 * i `certificates` są załadowane.
 */
final class ProfileCompleteness
{
    private function __construct(private readonly SpecialistProfile $profile) {}

    /**
     * Ocena kompletności dla danego profilu.
     */
    public static function for(SpecialistProfile $profile): self
    {
        return new self($profile);
    }

    /**
     * Kroki wymagane i czy są spełnione.
     *
     * @return array{data: bool, services: bool, area: bool}
     */
    public function required(): array
    {
        $profile = $this->profile;

        $hasName = filled($profile->display_name);
        $hasPhone = ! config('specialists.phone_required_to_publish') || filled($profile->phone);

        $radius = $profile->service_radius_km;
        $radiusOk = $radius >= (int) config('specialists.radius_min_km') && $radius <= (int) config('specialists.radius_max_km');

        return [
            'data' => $hasName && $hasPhone,
            'services' => $this->hasServices(),
            'area' => filled($profile->locality_simc) && $radiusOk,
        ];
    }

    /**
     * Kroki zalecane i czy są spełnione.
     *
     * @return array{description: bool, certificates: bool}
     */
    public function recommended(): array
    {
        return [
            'description' => filled($this->profile->description),
            'certificates' => $this->hasCertificates(),
        ];
    }

    /**
     * Czy wszystkie kroki wymagane są spełnione (profil można opublikować).
     */
    public function isComplete(): bool
    {
        return ! in_array(false, $this->required(), true);
    }

    /**
     * Klucze niespełnionych kroków wymaganych.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_keys(array_filter($this->required(), fn (bool $done): bool => ! $done));
    }

    /**
     * Liczba spełnionych kroków wymaganych (do napisu "Wymagane kroki: 2 z 3").
     */
    public function requiredDone(): int
    {
        return count(array_filter($this->required()));
    }

    /**
     * Liczba kroków wymaganych (zawsze 3).
     */
    public function requiredTotal(): int
    {
        return 3;
    }

    private function hasServices(): bool
    {
        return $this->profile->relationLoaded('services')
            ? $this->profile->services->isNotEmpty()
            : $this->profile->services()->exists();
    }

    private function hasCertificates(): bool
    {
        return $this->profile->relationLoaded('certificates')
            ? $this->profile->certificates->isNotEmpty()
            : $this->profile->certificates()->exists();
    }
}
