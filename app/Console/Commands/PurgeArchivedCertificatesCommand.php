<?php

namespace App\Console\Commands;

use App\Domain\Specialists\Actions\DeleteArchivedCertificate;
use App\Domain\Specialists\Models\ArchivedCertificate;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Usuwa z archiwum zaświadczeń (ADR-021) wpisy i pliki, którym minął termin `delete_after`
 * (domyślnie 12 miesięcy po usunięciu konta). Uruchamiane codziennie z harmonogramu.
 *
 * Do dziennika (log `retention`) trafia tylko liczba i ULID-y. Wpis przeterminowany o więcej niż 7 dni oznacza,
 * że cron nie działał: komenda zgłasza to jako błąd w logu aplikacji.
 */
class PurgeArchivedCertificatesCommand extends Command
{
    protected $signature = 'certificates:purge-archive';

    protected $description = 'Usuwa zarchiwizowane skany zaświadczeń po upływie okresu przechowywania';

    /** Po ilu dniach zaległości zgłaszamy błąd (cron nie działał). */
    private const OVERDUE_ALARM_DAYS = 7;

    /**
     * Kasuje przeterminowane wpisy porcjami.
     */
    public function handle(DeleteArchivedCertificate $delete): int
    {
        $ulids = [];
        $alarm = false;

        ArchivedCertificate::query()
            // Porównanie z datą-czasem (północ): kolumna `date` bywa zapisana z godziną 00:00:00 (SQLite w testach).
            ->where('delete_after', '<=', today())
            ->chunkById(100, function ($chunk) use ($delete, &$ulids, &$alarm): void {
                foreach ($chunk as $archived) {
                    /** @var ArchivedCertificate $archived */
                    if ($archived->delete_after->lt(today()->subDays(self::OVERDUE_ALARM_DAYS))) {
                        $alarm = true;
                    }

                    $ulids[] = $archived->ulid;
                    $delete->handle($archived);
                }
            });

        if ($alarm) {
            Log::error('Archiwum zaświadczeń: usunięto wpisy przeterminowane o więcej niż 7 dni (czy cron działa?).');
        }

        if ($ulids !== []) {
            AuditLogger::record('archive_purged', null, null, [
                'count' => count($ulids),
                'ulids' => $ulids,
            ], AuditLogger::LOG_RETENTION);
        }

        $this->info('Usunięto zarchiwizowanych zaświadczeń: '.count($ulids).'.');

        return self::SUCCESS;
    }
}
