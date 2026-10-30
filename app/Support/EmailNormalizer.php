<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Jedna kanoniczna postać adresu e-mail do kluczy liczników i limitów.
 *
 * Kolumna `users.email` ma collation utf8mb4_unicode_ci: baza traktuje `jo@x.pl` i `jó@x.pl` jako ten sam adres.
 * Klucze liczników muszą więc ignorować wielkość liter i akcenty, inaczej wariantami adresu da się obejść licznik.
 * (Transliteracja scala też niektóre znaki, które baza rozróżnia, np. "ł" i "l": to bezpieczny kierunek błędu.)
 */
final class EmailNormalizer
{
    /**
     * Zwraca postać kanoniczną: małe litery, bez białych znaków na brzegach, bez akcentów (ASCII).
     */
    public static function canonical(string $email): string
    {
        return Str::transliterate(Str::lower(trim($email)));
    }
}
