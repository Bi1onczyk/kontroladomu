<?php

namespace App\Domain\Geo\Data;

/**
 * Para współrzędnych WGS84 (stopnie dziesiętne).
 */
final readonly class Coordinates
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {}

    /**
     * Czy punkt leży w prostokącie Polski (`config/geocoding.php`, klucz `bounds`).
     */
    public function isInPoland(): bool
    {
        /** @var array{lat_min: float, lat_max: float, lng_min: float, lng_max: float} $bounds */
        $bounds = config('geocoding.bounds');

        return $this->lat >= $bounds['lat_min'] && $this->lat <= $bounds['lat_max']
            && $this->lng >= $bounds['lng_min'] && $this->lng <= $bounds['lng_max'];
    }
}
