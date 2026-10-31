<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Usunięcie pojedynczego zaświadczenia przy ŻYWYM koncie: wiersz i plik znikają od razu (po zatwierdzeniu transakcji).
 * Nie trafia do archiwum: archiwum 12 miesięcy dotyczy wyłącznie usunięcia całego konta ({@see PurgeSpecialistData}).
 * Kopie zapasowe są kasowane automatycznie po 14 dniach.
 */
class DeleteSpecialistCertificate
{
    public function handle(SpecialistCertificate $certificate, User $actor): void
    {
        $path = $certificate->file_path;
        $ulid = $certificate->ulid;

        DB::transaction(function () use ($certificate, $actor, $ulid): void {
            $certificate->delete();

            AuditLogger::record('certificate_deleted', $actor, null, [
                'ulid' => $ulid,
            ], AuditLogger::LOG_SPECIALIST);
        });

        // Plik kasujemy dopiero po zatwierdzeniu bazy: błąd bazy nie zostawia wiersza bez pliku.
        // Gdyby kasowanie pliku zawiodło, sprzątnie go cotygodniowa komenda certificates:prune-orphans.
        if (! Storage::disk('local')->delete($path)) {
            Log::warning('Nie udało się usunąć pliku zaświadczenia.', ['ulid' => $ulid]);
        }
    }
}
