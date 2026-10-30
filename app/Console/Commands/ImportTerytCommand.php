<?php

namespace App\Console\Commands;

use App\Domain\Geo\Actions\ImportTerytData;
use App\Domain\Geo\Exceptions\TerytImportException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Importuje gminy (TERC) i miejscowości (SIMC) z plików CSV pobranych z eTeryt (GUS).
 *
 * Pliki wgrywa się ręcznie do `storage/app/private/teryt-import/` (poza `public/`, nie trafiają do Git
 * ani do kopii zapasowej). Przykład:
 * `php artisan teryt:import --terc=teryt-import/TERC.csv --simc=teryt-import/SIMC.csv --dry-run`
 */
class ImportTerytCommand extends Command
{
    protected $signature = 'teryt:import
        {--terc= : Plik TERC (CSV); ścieżka względem storage/app/private albo bezwzględna}
        {--simc= : Plik SIMC (CSV); ścieżka względem storage/app/private albo bezwzględna}
        {--dry-run : Tylko policz zmiany, nic nie zapisuj}
        {--chunk=1000 : Wielkość porcji zapisu}
        {--resume-from= : Kod SIMC, od którego wznowić przerwany import}
        {--max-seconds= : Przerwij po bieżącej porcji po tylu sekundach (limit czasu hostingu)}';

    protected $description = 'Importuje gminy i miejscowości z plików TERC i SIMC (rejestr TERYT, GUS)';

    /**
     * Uruchamia import i wypisuje podsumowanie.
     */
    public function handle(ImportTerytData $import): int
    {
        $terc = $this->resolvePath((string) $this->option('terc'));
        $simc = $this->resolvePath((string) $this->option('simc'));

        if ($terc === null || $simc === null) {
            $this->error('Podaj istniejące pliki: --terc=... i --simc=... (CSV; ścieżka względem storage/app/private albo bezwzględna).');

            return self::INVALID;
        }

        $chunk = $this->positiveInteger($this->option('chunk'));
        $maxSeconds = $this->option('max-seconds') !== null ? $this->positiveInteger($this->option('max-seconds'), true) : null;

        if ($chunk === null || ($this->option('max-seconds') !== null && $maxSeconds === null)) {
            $this->error('Opcje --chunk i --max-seconds muszą być liczbami całkowitymi (--chunk większą od zera; --max-seconds może być 0) (np. --chunk=1000 --max-seconds=40).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $lastReported = 0;

        try {
            $result = $import->handle(
                tercPath: $terc,
                simcPath: $simc,
                dryRun: $dryRun,
                chunkSize: $chunk,
                resumeFrom: $this->option('resume-from') ?: null,
                maxSeconds: $maxSeconds,
                onProgress: function (string $stage, int $count) use (&$lastReported): void {
                    if ($stage === 'SIMC' && $count - $lastReported >= 10000) {
                        $lastReported = $count;
                        $this->line("  SIMC: przetworzono {$count} wierszy...");
                    }
                },
            );
        } catch (TerytImportException $e) {
            $this->error($e->getMessage());
            $this->line('Ponowne uruchomienie polecenia jest bezpieczne: import zapisuje dane metodą upsert, bez duplikatów. Porcje zapisane przed błędem zostają w bazie. Opcja --resume-from nie jest potrzebna po błędzie; służy do wznowienia po przerwaniu limitem czasu.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'TRYB PRÓBNY - nic nie zapisano.' : 'Import zakończony.');
        $this->line("Gminy: dodano {$result->municipalitiesAdded}, istniejących {$result->municipalitiesUpdated}.");
        $this->line("Miejscowości: dodano {$result->localitiesAdded}, istniejących {$result->localitiesUpdated}, pominięto (brak gminy) {$result->skippedWithoutMunicipality}.");

        if ($result->deactivationSkipped) {
            $this->line('Wyłączanie brakujących miejscowości: pominięte (import częściowy, wznowiony albo plik zawiera mniej niż 90% dotychczasowych miejscowości).');
        } else {
            $this->line("Wyłączono miejscowości nieobecne w pliku: {$result->deactivated}.");
        }

        $this->line('Szczytowe zużycie pamięci: '.round(memory_get_peak_usage(true) / 1048576, 1).' MB.');

        if (! $result->complete) {
            $this->warn("Przerwano po limicie czasu. Wznów poleceniem z opcją --resume-from={$result->resumeFrom}");
        }

        return self::SUCCESS;
    }

    /**
     * Zamienia wartość opcji na liczbę całkowitą dodatnią (z `$allowZero` także 0: dla --max-seconds oznacza "zakończ po pierwszej porcji"); null, gdy wartość jest nieprawidłowa.
     */
    private function positiveInteger(mixed $value, bool $allowZero = false): ?int
    {
        $text = is_int($value) ? (string) $value : $value;

        if (! is_string($text) || ! preg_match($allowZero ? '/^\d+$/' : '/^[1-9]\d*$/', $text) || strlen($text) > 9) {
            return null;
        }

        return (int) $text;
    }

    /**
     * Zamienia argument na istniejącą ścieżkę pliku (bezwzględną albo z dysku `local`); null, gdy pliku brak.
     */
    private function resolvePath(string $argument): ?string
    {
        if ($argument === '') {
            return null;
        }

        if (is_file($argument)) {
            return $argument;
        }

        $disk = Storage::disk('local');

        return $disk->exists($argument) ? $disk->path($argument) : null;
    }
}
