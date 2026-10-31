<?php

namespace App\Domain\Geo\Enums;

/**
 * Dokładność znalezionej pozycji: konkretny adres, ulica albo tylko środek miejscowości.
 */
enum GeocodePrecision: string
{
    case Address = 'address';
    case Street = 'street';
    case Locality = 'locality';

    /**
     * Dokładność na podstawie pola `addresstype` z odpowiedzi Nominatim (`house`, `road`, `village`...).
     */
    public static function fromAddressType(?string $addressType): self
    {
        return match ($addressType) {
            'house', 'building' => self::Address,
            'road', 'street' => self::Street,
            default => self::Locality,
        };
    }
}
