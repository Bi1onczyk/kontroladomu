<?php

namespace App\Domain\Specialists\Support;

/**
 * Polski numer telefonu: wejście w dowolnej formie ("600 123 456", "+48 600-123-456"), zapis w formacie +48XXXXXXXXX.
 */
final class PhoneNumber
{
    /**
     * Zamienia numer na format `+48XXXXXXXXX` albo zwraca null, gdy to nie jest polski numer (9 cyfr, pierwsza nie zero).
     */
    public static function normalize(string $input): ?string
    {
        $text = (string) preg_replace('/[\s\x{00A0}\-().]+/u', '', trim($input));

        if (! preg_match('/^(?:\+48)?([1-9]\d{8})$/', $text, $matches)) {
            return null;
        }

        return '+48'.$matches[1];
    }

    /**
     * Numer do wyświetlenia: "600 123 456" (z formatu `+48XXXXXXXXX`).
     */
    public static function display(string $normalized): string
    {
        $digits = substr($normalized, -9);

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 3);
    }
}
