<?php

namespace App\Domain\Specialists\Enums;

/**
 * Stan ustalania punktu startowego fachowca (środek miejscowości z TERYT, ADR-020).
 */
enum LocationStatus: string
{
    /** Czeka na współrzędne (kolejka albo brak konfiguracji dostawcy). */
    case Pending = 'pending';

    /** Współrzędne ustalone. */
    case Resolved = 'resolved';

    /** Geokodowanie nie powiodło się po wszystkich próbach. */
    case Failed = 'failed';
}
