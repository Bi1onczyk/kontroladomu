<?php

namespace App\Domain\Geo\Data;

/**
 * Podsumowanie importu TERYT (do wypisania przez komendę i do testów).
 */
final readonly class TerytImportResult
{
    /**
     * @param  bool  $complete  Czy przetworzono cały plik SIMC (false po przekroczeniu `--max-seconds`).
     * @param  string|null  $resumeFrom  Kod SIMC, od którego wznowić import, gdy `$complete` = false.
     * @param  int  $deactivated  Miejscowości wyłączone, bo zniknęły z pliku (tylko po pełnym imporcie).
     * @param  bool  $deactivationSkipped  Dezaktywacja pominięta (częściowy import albo plik < 90% dotychczasowych wierszy).
     * @param  int  $skippedWithoutMunicipality  Wiersze SIMC, których gminy nie ma w pliku TERC.
     */
    public function __construct(
        public bool $dryRun,
        public int $municipalitiesAdded,
        public int $municipalitiesUpdated,
        public int $localitiesAdded,
        public int $localitiesUpdated,
        public int $deactivated,
        public bool $deactivationSkipped,
        public int $skippedWithoutMunicipality,
        public bool $complete,
        public ?string $resumeFrom,
    ) {}
}
