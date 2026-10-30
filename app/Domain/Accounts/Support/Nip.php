<?php

namespace App\Domain\Accounts\Support;

/**
 * Normalizacja i sprawdzanie sumy kontrolnej polskiego NIP.
 *
 * Nie odpytuje żadnych rejestrów (GUS/CEIDG/VIES): decyzja właściciela z 29.10.2026.
 */
final class Nip
{
    /** Wagi cyfr 1-9 w algorytmie sumy kontrolnej. */
    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /**
     * Usuwa spacje, myślniki i prefiks PL; zostawia same cyfry.
     */
    public static function normalize(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        if (str_starts_with($value, 'PL')) {
            $value = substr($value, 2);
        }

        // Flaga u: twarda spacja (U+00A0) i półpauzy z kopiowania ze stron WWW też są usuwane.
        return preg_replace('/[\s\x{00A0}\-\x{2010}-\x{2015}]+/u', '', $value) ?? '';
    }

    /**
     * Czy podana (już znormalizowana lub nie) wartość to poprawny NIP: 10 cyfr i zgodna suma kontrolna.
     */
    public static function isValid(?string $value): bool
    {
        $nip = self::normalize($value);

        if (! preg_match('/^\d{10}$/', $nip)) {
            return false;
        }

        $sum = 0;
        foreach (self::WEIGHTS as $index => $weight) {
            $sum += $weight * (int) $nip[$index];
        }

        $control = $sum % 11;

        return $control !== 10 && $control === (int) $nip[9];
    }
}
