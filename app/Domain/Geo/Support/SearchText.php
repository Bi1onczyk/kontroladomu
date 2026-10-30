<?php

namespace App\Domain\Geo\Support;

use Illuminate\Support\Str;

/**
 * Normalizacja tekstu do wyszukiwania bez wrażliwości na wielkość liter i polskie znaki (ADR-012).
 *
 * Ta sama funkcja liczy kolumny `name_search` przy imporcie i normalizuje zapytanie użytkownika,
 * dzięki czemu "dabrowka", "Dąbrówka" i "DĄBRÓWKA" dają ten sam wynik na MySQL, MariaDB i SQLite.
 * Wynik zawiera wyłącznie znaki [a-z0-9] i pojedyncze spacje, więc nie ma w nim znaków specjalnych LIKE (% _ \).
 */
final class SearchText
{
    /**
     * Zamienia tekst na małe litery bez polskich znaków i interpunkcji.
     * Przykład: "Łódź-Bałuty" daje "lodz baluty".
     */
    public static function normalize(string $text): string
    {
        $ascii = Str::ascii(mb_strtolower($text));
        $clean = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim($clean);
    }
}
