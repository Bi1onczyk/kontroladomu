<?php

namespace App\Domain\Geo\Support;

use App\Domain\Geo\Exceptions\TerytImportException;
use Generator;

/**
 * Strumieniowy czytnik plików CSV z rejestru TERYT (TERC, SIMC).
 *
 * Format sprawdzony na prawdziwych plikach GUS z 30.10.2026: UTF-8 z BOM, separator średnik,
 * końce linii CRLF, pierwsza linia to nagłówek, pola NIE są ujęte w cudzysłowy. W danych zdarzają się
 * pojedyncze cudzysłowy w nazwach (np. `Mazewo Dworskie"B"`), dlatego NIE używamy `fgetcsv`
 * (traktowałby je jako ujęcie pola), tylko dzielimy linię po średniku.
 *
 * Kolumny są czytane po NAZWACH z nagłówka, nie po pozycji; nieznane kolumny są pomijane
 * (GUS zapowiada nowe atrybuty w plikach aktualizacyjnych). Plik jest czytany linia po linii,
 * więc zużycie pamięci nie zależy od jego rozmiaru.
 */
final class TerytCsvReader
{
    private const SEPARATOR = ';';

    /**
     * Zwraca kolejne wiersze jako tablice `nazwa kolumny => wartość`.
     *
     * @param  list<string>  $requiredColumns  Kolumny, bez których import nie ma sensu.
     * @return Generator<int, array<string, string>> Klucz to numer linii w pliku (nagłówek = 1).
     *
     * @throws TerytImportException Gdy plik nie istnieje, nie jest UTF-8, brakuje kolumny albo wiersz jest uszkodzony.
     */
    public static function rows(string $path, array $requiredColumns): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new TerytImportException("Nie można otworzyć pliku: {$path}");
        }

        try {
            $header = self::parseHeader($handle, $path, $requiredColumns);
            $width = count($header);
            $lineNumber = 1;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = rtrim($line, "\r\n");

                if ($line === '') {
                    continue;
                }

                if (! mb_check_encoding($line, 'UTF-8')) {
                    throw new TerytImportException("Linia {$lineNumber} pliku {$path} nie jest w UTF-8 (oczekiwane kodowanie UTF-8).");
                }

                $fields = explode(self::SEPARATOR, $line);

                if (count($fields) < $width) {
                    throw new TerytImportException("Linia {$lineNumber} pliku {$path} ma ".count($fields)." pól zamiast {$width}.");
                }

                yield $lineNumber => array_combine($header, array_slice($fields, 0, $width));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Czyta nagłówek (z usunięciem BOM) i sprawdza wymagane kolumny.
     *
     * @param  resource  $handle
     * @param  list<string>  $requiredColumns
     * @return list<string>
     */
    private static function parseHeader($handle, string $path, array $requiredColumns): array
    {
        $first = fgets($handle);

        if ($first === false) {
            throw new TerytImportException("Plik jest pusty: {$path}");
        }

        $first = preg_replace('/^\xEF\xBB\xBF/', '', rtrim($first, "\r\n")) ?? '';

        if (! mb_check_encoding($first, 'UTF-8')) {
            throw new TerytImportException("Nagłówek pliku {$path} nie jest w UTF-8 (oczekiwane kodowanie UTF-8).");
        }

        $header = array_map('trim', explode(self::SEPARATOR, $first));
        $missing = array_diff($requiredColumns, $header);

        if ($missing !== []) {
            throw new TerytImportException(
                "W pliku {$path} brakuje kolumn: ".implode(', ', $missing).'. Nagłówek: '.implode(self::SEPARATOR, $header)
                .'. Czy to na pewno plik CSV (nie XML) z separatorem średnik?'
            );
        }

        return $header;
    }
}
