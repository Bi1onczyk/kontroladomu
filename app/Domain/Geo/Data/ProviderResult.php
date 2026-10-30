<?php

namespace App\Domain\Geo\Data;

use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Enums\GeocodeStatus;

/**
 * Surowy wynik dostawcy geokodowania (przed zapisem do cache).
 * `Unavailable` niesie czas przerwy (`cooldownSeconds`), przez który nie wolno ponawiać zapytań.
 */
final readonly class ProviderResult
{
    private function __construct(
        public GeocodeStatus $status,
        public ?Coordinates $coordinates = null,
        public ?GeocodePrecision $precision = null,
        public int $cooldownSeconds = 0,
        public ?string $reason = null,
    ) {}

    public static function found(Coordinates $coordinates, GeocodePrecision $precision): self
    {
        return new self(GeocodeStatus::Found, $coordinates, $precision);
    }

    public static function notFound(): self
    {
        return new self(GeocodeStatus::NotFound);
    }

    /**
     * @param  string  $reason  Krótki kod przyczyny do logu (bez danych adresowych), np. `network`, `rate_limited`.
     */
    public static function unavailable(string $reason, int $cooldownSeconds = 0): self
    {
        return new self(GeocodeStatus::Unavailable, cooldownSeconds: $cooldownSeconds, reason: $reason);
    }
}
