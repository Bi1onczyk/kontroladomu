<?php

namespace App\Domain\Geo\Exceptions;

use RuntimeException;

/**
 * Błąd importu TERYT z czytelnym komunikatem po polsku (zły plik, brak kolumny, obcięty plik).
 * Komenda `teryt:import` wypisuje komunikat bez śladu stosu.
 */
class TerytImportException extends RuntimeException {}
