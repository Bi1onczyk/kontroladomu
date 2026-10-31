<?php

namespace App\Domain\Geo\Actions;

use App\Domain\Geo\Data\Coordinates;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Services\GeocodingService;

/**
 * Uzupełnia współrzędne miejscowości z TERYT (SIMC ich nie zawiera) po jej pierwszym wyborze.
 *
 * Jedno zapytanie do usługi geokodowania na miejscowość; wynik jest zapisywany w `teryt_localities`
 * (źródło `nominatim`), więc kolejne wybory nie wywołują sieci. Przeglądarka NIE woła Nominatim bezpośrednio.
 * Gdy pozycji nie da się ustalić, zwraca null i niczego nie zmienia (UX pokazuje ręczne ustawienie pinezki).
 */
class ResolveLocalityCoordinates
{
    public function __construct(private readonly GeocodingService $geocoding) {}

    /**
     * Zwraca współrzędne miejscowości (z bazy albo po geokodowaniu) lub null.
     */
    public function handle(TerytLocality $locality): ?Coordinates
    {
        if ($locality->hasCoordinates()) {
            return new Coordinates((float) $locality->lat, (float) $locality->lng);
        }

        $coordinates = $this->geocoding->geocodeLocality($locality);

        if ($coordinates === null) {
            return null;
        }

        $locality->forceFill([
            'lat' => round($coordinates->lat, 7),
            'lng' => round($coordinates->lng, 7),
            'coordinates_source' => 'nominatim',
            'geocoded_at' => now(),
        ])->save();

        return $coordinates;
    }
}
