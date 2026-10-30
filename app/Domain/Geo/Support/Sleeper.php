<?php

namespace App\Domain\Geo\Support;

/**
 * Zegar i usypianie procesu w jednym miejscu, żeby testy mogły podmienić czas (bez prawdziwego `usleep`).
 * Używany przez {@see NominatimThrottle}.
 */
class Sleeper
{
    /**
     * Aktualny czas w sekundach z ułamkiem (mikrosekundy).
     */
    public function now(): float
    {
        return microtime(true);
    }

    /**
     * Usypia proces na podaną liczbę milisekund.
     */
    public function sleepMilliseconds(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
