<?php

namespace App\Domain\Specialists\Models;

use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Enums\AccountType;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Policies\SpecialistProfilePolicy;
use App\Models\User;
use Database\Factories\SpecialistProfileFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Profil fachowca: jeden na konto z rolą `specialist` (zadanie 004).
 *
 * `$fillable` zawiera WYŁĄCZNIE pola, które fachowiec edytuje sam. Pola stanu (`slug`, `locality_simc`, `start_*`,
 * `location_*`, `verification_*`, `is_published`, `first_published_at`, `phone_public_consent_at`) ustawiają
 * tylko akcje przez `forceFill`, nigdy dane z formularza.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property Carbon|null $slug_changed_at
 * @property string $display_name
 * @property AccountType $account_type
 * @property string|null $description
 * @property string|null $phone
 * @property bool $phone_is_public
 * @property Carbon|null $phone_public_consent_at
 * @property string|null $locality_simc
 * @property int $service_radius_km
 * @property int $travel_rate_per_km_grosze
 * @property int $travel_free_km
 * @property string|null $start_lat
 * @property string|null $start_lng
 * @property GeocodePrecision|null $start_precision
 * @property string|null $start_source
 * @property LocationStatus $location_status
 * @property Carbon|null $location_resolved_at
 * @property VerificationStatus $verification_status
 * @property Carbon|null $verified_at
 * @property bool $is_published
 * @property Carbon|null $first_published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TerytLocality|null $locality
 */
#[UseFactory(SpecialistProfileFactory::class)]
#[UsePolicy(SpecialistProfilePolicy::class)]
class SpecialistProfile extends Model
{
    /** @use HasFactory<SpecialistProfileFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'display_name',
        'account_type',
        'description',
        'phone',
        'phone_is_public',
        'service_radius_km',
        'travel_rate_per_km_grosze',
        'travel_free_km',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'location_status' => LocationStatus::class,
            'verification_status' => VerificationStatus::class,
            'start_precision' => GeocodePrecision::class,
            'phone_is_public' => 'boolean',
            'is_published' => 'boolean',
            'slug_changed_at' => 'datetime',
            'phone_public_consent_at' => 'datetime',
            'location_resolved_at' => 'datetime',
            'verified_at' => 'datetime',
            'first_published_at' => 'datetime',
            'service_radius_km' => 'integer',
            'travel_rate_per_km_grosze' => 'integer',
            'travel_free_km' => 'integer',
        ];
    }

    /**
     * Konto właściciela profilu.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Miejscowość startowa z TERYT (klucz: kod SIMC).
     *
     * @return BelongsTo<TerytLocality, $this>
     */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(TerytLocality::class, 'locality_simc', 'simc');
    }

    /**
     * Pozycje cennika (rodzaj przeglądu x typ nieruchomości).
     *
     * @return HasMany<SpecialistService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(SpecialistService::class);
    }

    /**
     * Zaświadczenia o uprawnieniach.
     *
     * @return HasMany<SpecialistCertificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(SpecialistCertificate::class);
    }

    /**
     * Dawne adresy profilu przekierowujące na bieżący.
     *
     * @return HasMany<SpecialistSlugRedirect, $this>
     */
    public function slugRedirects(): HasMany
    {
        return $this->hasMany(SpecialistSlugRedirect::class);
    }

    /**
     * Czy profil jest widoczny dla każdego: opublikowany, w stanie weryfikacji dopuszczającym widoczność,
     * a właściciel nie jest zablokowany.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->is_published
            && $this->verification_status->isPubliclyVisible()
            && ! $this->user->isBanned();
    }

    /**
     * Pełny adres publiczny profilu.
     */
    public function publicUrl(): string
    {
        return route('specialists.show', ['slug' => $this->slug]);
    }
}
