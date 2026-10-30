<?php

namespace App\Domain\Geo\Data;

use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Enums\GeocodeStatus;

/**
 * Wynik geokodowania zwracany aplikacji. Serwis nigdy nie rzuca wyjątku do interfejsu:
 * awarię sygnalizuje statusem `Unavailable` (wywołujący zapisuje obiekt bez współrzędnych
 * i ponawia później albo używa środka miejscowości).
 */
final readonly class GeocodeResult
{
    private function __construct(
        public GeocodeStatus $status,
        public ?float $lat,
        public ?float $lng,
        public ?GeocodePrecision $precision,
        public bool $fromCache,
        public ?string $reason = null,
    ) {}

    public static function found(float $lat, float $lng, GeocodePrecision $precision, bool $fromCache): self
    {
        return new self(GeocodeStatus::Found, $lat, $lng, $precision, $fromCache);
    }

    public static function notFound(bool $fromCache = false): self
    {
        return new self(GeocodeStatus::NotFound, null, null, null, $fromCache);
    }

    public static function unavailable(string $reason): self
    {
        return new self(GeocodeStatus::Unavailable, null, null, null, false, $reason);
    }

    public function isFound(): bool
    {
        return $this->status === GeocodeStatus::Found;
    }

    /**
     * Współrzędne znalezionej pozycji albo null.
     */
    public function coordinates(): ?Coordinates
    {
        return $this->isFound() ? new Coordinates((float) $this->lat, (float) $this->lng) : null;
    }
}
