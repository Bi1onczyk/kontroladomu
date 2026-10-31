<?php

namespace App\Domain\Geo\Enums;

/**
 * Wynik geokodowania. Do bazy (`geocode_cache.status`) trafiają tylko `Found` i `NotFound`;
 * `Unavailable` (błąd sieci, limit, brak konfiguracji) nigdy nie jest zapisywany.
 */
enum GeocodeStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';
}
