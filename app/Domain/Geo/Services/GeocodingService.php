<?php

namespace App\Domain\Geo\Services;

use App\Domain\Geo\Actions\ResolveLocalityCoordinates;
use App\Domain\Geo\Contracts\GeocodingProvider;
use App\Domain\Geo\Data\AddressQuery;
use App\Domain\Geo\Data\Coordinates;
use App\Domain\Geo\Data\GeocodeResult;
use App\Domain\Geo\Data\ProviderResult;
use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Enums\GeocodeStatus;
use App\Domain\Geo\Models\GeocodeCacheEntry;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Support\NominatimThrottle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publiczne API geokodowania w aplikacji (ADR-013).
 *
 * Kolejność: (1) wynik z cache (`geocode_cache`, klucz = skrót adresu, bez ruchu sieciowego), (2) sprawdzenie,
 * czy dostawca jest skonfigurowany, (3) wyłącznik awarii (`geocoding:down`), (4) zapytanie przez limit 1/s,
 * (5) zapis do cache. Serwis NIGDY nie rzuca wyjątku do interfejsu; awarię zgłasza statusem `Unavailable`.
 * Błędy sieci nie są zapisywane w cache jako "nie znaleziono".
 *
 * Wywołujący (np. zapis nieruchomości) przy `Unavailable` zapisuje obiekt bez współrzędnych i ponawia później
 * (zadanie w kolejce) albo używa środka miejscowości z TERYT.
 */
class GeocodingService
{
    /** Klucz wyłącznika: gdy istnieje w cache, serwis nie wysyła zapytań. */
    private const DOWN_KEY = 'geocoding:down';

    public function __construct(
        private readonly GeocodingProvider $provider,
        private readonly NominatimThrottle $throttle,
    ) {}

    /**
     * Zamienia adres na współrzędne. Po braku wyniku dla adresu z ulicą próbuje jeszcze samej miejscowości
     * (dokładność `locality`).
     */
    public function geocodeAddress(AddressQuery $query): GeocodeResult
    {
        if (! $query->isSearchable()) {
            return GeocodeResult::notFound();
        }

        $hash = $query->hash();

        if (($cached = $this->fromCache($hash)) !== null) {
            return $cached;
        }

        if (! $this->provider->isConfigured()) {
            $this->logOnce('not_configured', $hash, 86400);

            return GeocodeResult::unavailable('not_configured');
        }

        if (Cache::has(self::DOWN_KEY)) {
            return GeocodeResult::unavailable('cooldown');
        }

        $cachedMeanwhile = null;
        $result = $this->search($query, $hash, $cachedMeanwhile);

        // Wynik zapisany przez inny proces w czasie oczekiwania na blokadę limitu (bez zapytania do usługi).
        if ($cachedMeanwhile instanceof GeocodeResult) {
            return $cachedMeanwhile;
        }

        // Brak adresu z ulicą: spróbuj samej miejscowości (jeszcze jedno zapytanie, też przez limit 1/s).
        if ($result->status === GeocodeStatus::NotFound && $query->hasStreet() && $query->withoutStreet()->isSearchable()) {
            $fallback = $this->search($query->withoutStreet(), $hash);

            if ($fallback->status === GeocodeStatus::Found && $fallback->coordinates !== null) {
                $result = ProviderResult::found($fallback->coordinates, GeocodePrecision::Locality);
            } elseif ($fallback->status === GeocodeStatus::Unavailable) {
                $result = $fallback;
            }
        }

        return $this->finish($result, $hash);
    }

    /**
     * Współrzędne środka miejscowości z bazy TERYT (znane z bazy albo z geokodowania). Niczego nie zapisuje
     * w tabeli miejscowości: robi to {@see ResolveLocalityCoordinates}.
     * Zwraca null, gdy pozycji nie udało się ustalić (nie znaleziono lub usługa niedostępna).
     */
    public function geocodeLocality(TerytLocality $locality): ?Coordinates
    {
        if ($locality->hasCoordinates()) {
            return new Coordinates((float) $locality->lat, (float) $locality->lng);
        }

        return $this->geocodeAddress(AddressQuery::forLocality($locality))->coordinates();
    }

    /**
     * Niewygasły wpis z cache albo null.
     */
    private function fromCache(string $hash): ?GeocodeResult
    {
        $entry = GeocodeCacheEntry::query()
            ->where('query_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();

        if ($entry === null) {
            return null;
        }

        if ($entry->status === GeocodeStatus::Found && $entry->lat !== null && $entry->lng !== null) {
            return GeocodeResult::found((float) $entry->lat, (float) $entry->lng, $entry->precision ?? GeocodePrecision::Locality, true);
        }

        return GeocodeResult::notFound(fromCache: true);
    }

    /**
     * Jedno zapytanie do dostawcy przez limit 1/s. Wyjątek dostawcy nie wychodzi na zewnątrz.
     */
    private function search(AddressQuery $query, string $hash, ?GeocodeResult &$cachedMeanwhile = null): ProviderResult
    {
        try {
            $result = $this->throttle->run(function () use ($query, $hash, &$cachedMeanwhile): ProviderResult {
                // Po zdobyciu blokady (i odczekaniu odstępu) inny proces mógł już zapisać ten adres: nie pytamy usługi drugi raz.
                $cachedMeanwhile = $this->fromCache($hash);

                if ($cachedMeanwhile !== null) {
                    return ProviderResult::unavailable('cached');
                }

                return $this->provider->search($query);
            });
        } catch (Throwable $e) {
            // Log bez adresu: tylko klasa błędu i skrót zapytania.
            Log::error('Geokodowanie: nieoczekiwany błąd dostawcy.', ['exception' => $e::class, 'query_hash' => $hash]);

            return ProviderResult::unavailable('error', (int) config('geocoding.cooldown_seconds'));
        }

        // Nie zdobyto blokady limitu: nie wykonujemy zapytania, żeby nie przekroczyć 1/s.
        return $result ?? ProviderResult::unavailable('busy');
    }

    /**
     * Zapisuje wynik w cache (poza niedostępnością) i zamienia go na wynik dla aplikacji.
     */
    private function finish(ProviderResult $result, string $hash): GeocodeResult
    {
        if ($result->status === GeocodeStatus::Unavailable) {
            $reason = $result->reason ?? 'unknown';

            if ($result->cooldownSeconds > 0) {
                Cache::put(self::DOWN_KEY, $reason, $result->cooldownSeconds);
                $this->logOnce($reason, $hash, $result->cooldownSeconds);
            }

            return GeocodeResult::unavailable($reason);
        }

        if ($result->status === GeocodeStatus::Found && $result->coordinates !== null) {
            $precision = $result->precision ?? GeocodePrecision::Locality;
            $this->store($hash, GeocodeStatus::Found, $result->coordinates, $precision, (int) config('geocoding.ttl_found_days'));

            return GeocodeResult::found($result->coordinates->lat, $result->coordinates->lng, $precision, false);
        }

        $this->store($hash, GeocodeStatus::NotFound, null, null, (int) config('geocoding.ttl_not_found_days'));

        return GeocodeResult::notFound();
    }

    /**
     * Zapisuje lub odnawia wpis cache (jeden wiersz na skrót; wygasły wpis jest nadpisywany).
     */
    private function store(string $hash, GeocodeStatus $status, ?Coordinates $coordinates, ?GeocodePrecision $precision, int $ttlDays): void
    {
        $now = now()->toDateTimeString();

        try {
            // upsert (jedno zapytanie INSERT ... ON DUPLICATE KEY UPDATE) zamiast updateOrCreate: równoległy zapis
            // tego samego skrótu nie kończy się błędem UNIQUE.
            GeocodeCacheEntry::query()->upsert([[
                'query_hash' => $hash,
                'status' => $status->value,
                'lat' => $coordinates !== null ? round($coordinates->lat, 7) : null,
                'lng' => $coordinates !== null ? round($coordinates->lng, 7) : null,
                'precision' => $precision?->value,
                'provider' => (string) config('geocoding.provider'),
                'expires_at' => now()->addDays($ttlDays)->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['query_hash'], ['status', 'lat', 'lng', 'precision', 'provider', 'expires_at', 'updated_at']);
        } catch (Throwable $e) {
            // Brak zapisu cache nie może przewrócić geokodowania; wynik i tak wraca do wywołującego.
            Log::warning('Geokodowanie: nie zapisano wyniku w cache.', ['exception' => $e::class, 'query_hash' => $hash]);
        }
    }

    /**
     * Jedna linia ostrzeżenia na okno czasowe (nie na każde żądanie). Bez danych adresowych: tylko przyczyna i skrót.
     */
    private function logOnce(string $reason, string $hash, int $windowSeconds): void
    {
        if (Cache::add('geocoding:logged:'.$reason, true, max(60, $windowSeconds))) {
            Log::warning('Geokodowanie niedostępne.', ['reason' => $reason, 'query_hash' => $hash]);
        }
    }
}
