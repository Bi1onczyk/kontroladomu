<?php

namespace Database\Factories;

use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Enums\AccountType;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpecialistProfile>
 */
class SpecialistProfileFactory extends Factory
{
    protected $model = SpecialistProfile::class;

    /**
     * Dane SYNTETYCZNE. Domyślnie profil pusty, nieopublikowany, oczekujący na weryfikację.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory()->specialist(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'display_name' => $name,
            'account_type' => AccountType::SoleProprietor,
            'service_radius_km' => 25,
            'travel_rate_per_km_grosze' => 0,
            'travel_free_km' => 0,
            'location_status' => LocationStatus::Pending,
            'verification_status' => VerificationStatus::Pending,
            'is_published' => false,
        ];
    }

    /** Profil z uzupełnionymi trzema wymaganymi krokami poza usługami (nazwa, telefon, miejscowość). */
    public function withLocality(?TerytLocality $locality = null): static
    {
        return $this->state(function () use ($locality): array {
            $locality ??= TerytLocality::factory()->withCoordinates()->create();

            return [
                'locality_simc' => $locality->simc,
                'start_lat' => $locality->lat,
                'start_lng' => $locality->lng,
                'start_precision' => GeocodePrecision::Locality,
                'start_source' => 'locality_center',
                'location_status' => $locality->hasCoordinates() ? LocationStatus::Resolved : LocationStatus::Pending,
                'location_resolved_at' => $locality->hasCoordinates() ? now() : null,
            ];
        });
    }

    /** Numer prywatny (opcjonalnie publiczny za zgodą). */
    public function withPhone(bool $public = false): static
    {
        return $this->state([
            'phone' => '+48600123456',
            'phone_is_public' => $public,
            'phone_public_consent_at' => $public ? now() : null,
        ]);
    }

    /** Opublikowany profil oczekujący na weryfikację. */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'first_published_at' => now()->subDay(),
        ]);
    }

    /** Opublikowany i zweryfikowany profil. */
    public function verified(): static
    {
        return $this->published()->state(fn (): array => [
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now()->subHour(),
        ]);
    }

    /** Stan weryfikacji ustawiony wprost (np. odrzucony). */
    public function withVerification(VerificationStatus $status): static
    {
        return $this->state([
            'verification_status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now()->subHour() : null,
        ]);
    }
}
