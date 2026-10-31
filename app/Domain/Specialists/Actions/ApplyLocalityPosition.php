<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Models\SpecialistProfile;

/**
 * Kopiuje współrzędne środka miejscowości (z `teryt_localities`) do wszystkich profili z tą miejscowością,
 * którym jeszcze brakuje pozycji (stan `pending` lub `failed`). Jedno zapytanie UPDATE; zero ruchu sieciowego.
 *
 * Wywołują ją: zapis obszaru działania (współrzędne już znane), `GeocodeLocalityJob` (po ustaleniu pozycji)
 * i ponowienie ręczne.
 */
class ApplyLocalityPosition
{
    /**
     * Zwraca liczbę zaktualizowanych profili (0, gdy miejscowość nie ma współrzędnych).
     */
    public function handle(TerytLocality $locality): int
    {
        if (! $locality->hasCoordinates()) {
            return 0;
        }

        return SpecialistProfile::query()
            ->where('locality_simc', $locality->simc)
            ->where('location_status', '!=', LocationStatus::Resolved->value)
            ->update([
                'start_lat' => $locality->lat,
                'start_lng' => $locality->lng,
                'start_precision' => GeocodePrecision::Locality->value,
                'start_source' => 'locality_center',
                'location_status' => LocationStatus::Resolved->value,
                'location_resolved_at' => now(),
            ]);
    }
}
