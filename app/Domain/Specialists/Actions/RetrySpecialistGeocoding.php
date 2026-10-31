<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Jobs\GeocodeLocalityJob;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ręczne ponowienie ustalania położenia miejscowości ("Spróbuj ponownie" w panelu).
 *
 * Gdy miejscowość ma już współrzędne, kopiuje je od razu. W przeciwnym razie ustawia profil jako `pending`
 * (żeby UI przestał pokazywać błąd) i zleca zadanie w kolejce. Limit 3 ponowienia na godzinę
 * pilnuje middleware `throttle:geocode-retry` na trasie.
 */
class RetrySpecialistGeocoding
{
    public function __construct(private readonly ApplyLocalityPosition $applyPosition) {}

    /**
     * Zwraca true, gdy zlecono ponowienie albo uzupełniono pozycję; false, gdy profil nie ma miejscowości
     * albo pozycja jest już ustalona.
     */
    public function handle(SpecialistProfile $profile, User $actor): bool
    {
        if ($profile->locality_simc === null || $profile->location_status === LocationStatus::Resolved) {
            return false;
        }

        $locality = TerytLocality::query()->where('simc', $profile->locality_simc)->first();

        if ($locality === null) {
            return false;
        }

        DB::transaction(function () use ($profile, $locality, $actor): void {
            if ($locality->hasCoordinates()) {
                $this->applyPosition->handle($locality);
            } else {
                $profile->forceFill(['location_status' => LocationStatus::Pending])->save();
            }

            AuditLogger::record('geocoding_retry_requested', $actor, $profile, [], AuditLogger::LOG_SPECIALIST);
        });

        if (! $locality->hasCoordinates()) {
            GeocodeLocalityJob::dispatch($locality->simc);
        }

        return true;
    }
}
