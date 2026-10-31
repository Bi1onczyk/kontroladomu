<?php

namespace App\Console\Commands;

use App\Domain\Specialists\Actions\PurgeSpecialistData;
use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Domain\Specialists\Models\SpecialistCertificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sprząta pliki zaświadczeń bez wiersza w bazie (np. po przerwanym wgraniu albo usunięciu wiersza kaskadą).
 * Dwa katalogi niezależnie: aktywne zaświadczenia i archiwum po usunięciu konta. Usuwa tylko pliki starsze niż
 * 1 dzień, żeby nie skasować pliku, którego wiersz jest właśnie zapisywany. Uruchamiane co tydzień.
 */
class PruneCertificateOrphansCommand extends Command
{
    protected $signature = 'certificates:prune-orphans';

    protected $description = 'Usuwa pliki zaświadczeń bez wiersza w bazie (starsze niż 1 dzień)';

    /**
     * Przegląda oba katalogi i usuwa osierocone pliki.
     */
    public function handle(): int
    {
        $removed = $this->prune('specialist-certificates', fn (array $paths): array => SpecialistCertificate::query()
            ->whereIn('file_path', $paths)->pluck('file_path')->all());

        $removed += $this->prune(PurgeSpecialistData::ARCHIVE_DIRECTORY, fn (array $paths): array => ArchivedCertificate::query()
            ->whereIn('file_path', $paths)->pluck('file_path')->all());

        if ($removed > 0) {
            Log::warning('Usunięto osierocone pliki zaświadczeń.', ['count' => $removed]);
        }

        $this->info("Usunięto osieroconych plików: {$removed}.");

        return self::SUCCESS;
    }

    /**
     * Usuwa z katalogu pliki, których ścieżki nie ma w bazie (porcjami po 200, bez zapytania na plik).
     *
     * @param  callable(array<int, string>): array<int, string>  $existingPaths  Zwraca ścieżki z danej porcji, które mają wiersz.
     */
    private function prune(string $directory, callable $existingPaths): int
    {
        $disk = Storage::disk('local');
        $threshold = now()->subDay()->getTimestamp();
        $removed = 0;

        foreach (array_chunk($disk->files($directory), 200) as $paths) {
            $known = array_flip($existingPaths($paths));

            foreach ($paths as $path) {
                if (isset($known[$path]) || $disk->lastModified($path) > $threshold) {
                    continue;
                }

                $disk->delete($path);
                $removed++;
            }
        }

        return $removed;
    }
}
