<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Usunięcie danych profilu fachowca przy usuwaniu konta, z archiwizacją skanów zaświadczeń (ADR-021).
 *
 * W jednej transakcji: pliki zaświadczeń są PRZENOSZONE (nie kopiowane) do katalogu archiwum, metadane trafiają do
 * `archived_certificates` (rodzaj, zakres, numer, wydawca, ważność, nazwa publiczna, status weryfikacji; bez
 * telefonu, e-maila, opisu, NIP i cennika), a profil jest kasowany (kaskada usuwa cennik, przekierowania i
 * zaświadczenia). Archiwum czyści po `delete_after` komenda `certificates:purge-archive` (domyślnie 12 miesięcy,
 * `specialists.archive_retention_months`; podstawa prawna do weryfikacji przez prawnika).
 *
 * Błąd w środku cofa transakcję, a przeniesione pliki wracają na miejsce (best effort); akcja rzuca wyjątek,
 * więc wywołujący NIE może usunąć konta. W zadaniu 004 akcja nie jest podpięta do żadnego ekranu: wywoła ją
 * przyszłe zadanie usuwania konta PRZED skasowaniem użytkownika.
 */
class PurgeSpecialistData
{
    /** Katalog archiwum na dysku `local`. */
    public const ARCHIVE_DIRECTORY = 'specialist-certificates-archive';

    /**
     * Zwraca liczbę zarchiwizowanych zaświadczeń (0, gdy konto nie miało profilu albo zaświadczeń).
     *
     * @throws Throwable Gdy archiwizacja się nie powiodła (konta nie wolno wtedy usuwać).
     */
    public function handle(User $user): int
    {
        $profile = SpecialistProfile::query()->with('certificates')->where('user_id', $user->id)->first();

        if ($profile === null) {
            return 0;
        }

        $disk = Storage::disk('local');
        $moved = [];
        $archivedAt = now();
        $deleteAfter = $archivedAt->copy()->addMonthsNoOverflow((int) config('specialists.archive_retention_months'));

        try {
            DB::transaction(function () use ($profile, $user, $disk, &$moved, $archivedAt, $deleteAfter): void {
                foreach ($profile->certificates as $certificate) {
                    if (! $disk->exists($certificate->file_path)) {
                        // Rozjazd bazy i dysku: nie ma czego archiwizować. Zapisujemy tylko ULID.
                        Log::warning('Brak pliku zaświadczenia przy usuwaniu konta; pominięto archiwizację.', ['ulid' => $certificate->ulid]);

                        continue;
                    }

                    $target = self::ARCHIVE_DIRECTORY.'/'.basename($certificate->file_path);

                    if (! $disk->move($certificate->file_path, $target)) {
                        throw new \RuntimeException('Nie udało się przenieść pliku zaświadczenia do archiwum.');
                    }

                    $moved[$target] = $certificate->file_path;

                    // `move()` zachowuje czas modyfikacji pliku, a wiersz archiwum jest zatwierdzony dopiero na końcu
                    // transakcji. Świeży czas sprawia, że `certificates:prune-orphans` (pomija pliki młodsze niż
                    // 1 dzień) nie uzna pliku w tym oknie za osierocony (audyt 004: wyścig).
                    @touch($disk->path($target));

                    ArchivedCertificate::query()->create([
                        'ulid' => $certificate->ulid,
                        'former_user_id' => $user->id,
                        'holder_label' => $profile->display_name,
                        'type' => $certificate->type,
                        'title' => $certificate->title,
                        'number' => $certificate->number,
                        'issuer' => $certificate->issuer,
                        'valid_until' => $certificate->valid_until,
                        'verification_status_at_archive' => $profile->verification_status,
                        'verified_at' => $profile->verified_at,
                        'file_path' => $target,
                        'file_mime' => $certificate->file_mime,
                        'file_size' => $certificate->file_size,
                        'file_sha256' => $certificate->file_sha256,
                        'archived_at' => $archivedAt,
                        'delete_after' => $deleteAfter->toDateString(),
                    ]);
                }

                $profile->delete();

                // Bez numerów zaświadczeń i danych kontaktowych: liczba i termin końca przechowywania.
                AuditLogger::record('specialist_data_purged', null, null, [
                    'former_user_id' => $user->id,
                    'archived_certificates' => count($moved),
                    'delete_after' => $deleteAfter->toDateString(),
                ], AuditLogger::LOG_SPECIALIST);
            });
        } catch (Throwable $e) {
            $this->restoreFiles($moved);

            throw $e;
        }

        return count($moved);
    }

    /**
     * Przenosi pliki z powrotem na miejsce po nieudanej archiwizacji (best effort; błąd jest logowany).
     *
     * @param  array<string, string>  $moved  Mapa: ścieżka w archiwum => ścieżka pierwotna.
     */
    private function restoreFiles(array $moved): void
    {
        $disk = Storage::disk('local');

        foreach ($moved as $archivePath => $originalPath) {
            try {
                $disk->move($archivePath, $originalPath);
            } catch (Throwable $e) {
                Log::error('Nie udało się przywrócić pliku po nieudanej archiwizacji.', ['exception' => $e::class, 'path' => $archivePath]);
            }
        }
    }
}
