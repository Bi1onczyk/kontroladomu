<?php

namespace App\Domain\Geo\Contracts;

use App\Domain\Geo\Data\AddressQuery;
use App\Domain\Geo\Data\ProviderResult;

/**
 * Dostawca geokodowania (obecnie Nominatim). Interfejs pozwala podmienić usługę bez zmian w aplikacji (ADR-002, ADR-013).
 */
interface GeocodingProvider
{
    /**
     * Czy dostawca może wysyłać zapytania (np. skonfigurowano wymagany adres kontaktowy).
     * Bez tego serwis korzysta wyłącznie z cache i nie wykonuje żadnego ruchu sieciowego.
     */
    public function isConfigured(): bool;

    /**
     * Szuka jednej, najlepszej pozycji dla adresu. Nie rzuca wyjątków sieciowych: zwraca `ProviderResult::unavailable()`.
     */
    public function search(AddressQuery $query): ProviderResult;
}
