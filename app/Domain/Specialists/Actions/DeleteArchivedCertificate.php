<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Usunięcie wpisu archiwum zaświadczeń (plik i wiersz). Idempotentne: brak pliku to tylko ostrzeżenie w logu.
 *
 * Używane przez komendę `certificates:purge-archive` (po upływie `delete_after`; komenda sama zapisuje zbiorczy wpis
 * w dzienniku) oraz przez administratora (wcześniejsze usunięcie, np. po uwzględnieniu sprzeciwu z art. 21 RODO;
 * wtedy podaje się `$actor` i powód, a akcja zapisuje wpis w dzienniku administratora).
 */
class DeleteArchivedCertificate
{
    /**
     * @param  User|null  $actor  Administrator usuwający wcześniej; null = automatyczne czyszczenie po terminie.
     * @param  string|null  $reason  Powód wcześniejszego usunięcia (np. `objection`); bez danych osobowych.
     */
    public function handle(ArchivedCertificate $archived, ?User $actor = null, ?string $reason = null): void
    {
        $ulid = $archived->ulid;
        $path = $archived->file_path;

        if (! Storage::disk('local')->delete($path)) {
            Log::warning('Nie udało się usunąć pliku z archiwum zaświadczeń (brak pliku?).', ['ulid' => $ulid]);
        }

        DB::transaction(function () use ($archived, $actor, $ulid, $reason): void {
            $archived->delete();

            if ($actor !== null) {
                AuditLogger::record('archived_certificate_deleted', $actor, null, [
                    'ulid' => $ulid,
                    'reason' => $reason,
                ], AuditLogger::LOG_ADMIN);
            }
        });
    }
}
