<?php

namespace App\Domain\Specialists\Support;

/**
 * Zamiana kwot między zapisem "złoty z przecinkiem" (formularz, widok) a groszami (baza). Kwoty nigdy nie są float.
 */
final class Grosze
{
    /**
     * Zamienia tekst z formularza ("180", "180,50", "180.5", "1 200 zł") na grosze.
     * Zwraca null, gdy tekst nie jest kwotą (litery, więcej niż 2 miejsca po przecinku, wartość ujemna).
     */
    public static function parse(string $input): ?int
    {
        $text = trim($input);
        // Spacje zwykłe, twarde i wąskie jako separatory tysięcy oraz końcówka "zł".
        $text = (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text);
        $text = (string) preg_replace('/(zł|zl|pln)$/iu', '', $text);

        if (! preg_match('/^(\d{1,7})(?:[.,](\d{1,2}))?$/', $text, $matches)) {
            return null;
        }

        $zloty = (int) $matches[1];
        $fraction = isset($matches[2]) ? (int) str_pad($matches[2], 2, '0') : 0;

        return $zloty * 100 + $fraction;
    }

    /**
     * Zamienia grosze na tekst bez symbolu waluty: "180" albo "180,50" (do pola formularza i do widoku).
     */
    public static function format(int $grosze): string
    {
        $zloty = intdiv($grosze, 100);
        $fraction = $grosze % 100;

        return $fraction === 0 ? (string) $zloty : $zloty.','.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Zapis ze znakiem waluty, np. "180,50 zł" (spacja twarda, żeby kwota nie łamała się w wierszu).
     */
    public static function formatWithCurrency(int $grosze): string
    {
        return self::format($grosze)."\u{00A0}zł";
    }
}
