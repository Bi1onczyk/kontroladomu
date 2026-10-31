<?php

namespace App\Domain\Specialists\Enums;

/**
 * Stan karty "Publikacja" w panelu fachowca (sześć stanów z projektu UX, ekran 3.6).
 */
enum PublicationState: string
{
    /** Brakuje wymaganych kroków; brak przycisku publikacji. */
    case Incomplete = 'incomplete';

    /** Kroki spełnione, profil jeszcze nigdy nie był opublikowany. */
    case Ready = 'ready';

    /** Opublikowany, weryfikacja oczekuje. */
    case LivePending = 'live_pending';

    /** Opublikowany i zweryfikowany. */
    case LiveVerified = 'live_verified';

    /** Ukryty przez fachowca (był publikowany), kroki spełnione: przycisk "Opublikuj ponownie". */
    case Hidden = 'hidden';

    /** Weryfikacja odrzucona lub cofnięta: profil niewidoczny, brak przycisku. */
    case Blocked = 'blocked';
}
