<?php

namespace App\Console\Commands;

use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Czyści dziennik aktywności zgodnie z retencją: 90 dni dla zdarzeń zwykłych,
 * 12 miesięcy dla działań administratorów (do potwierdzenia z prawnikiem).
 */
class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune {--days=90 : Retencja zwykłych wpisów} {--admin-days=365 : Retencja działań administratorów}';

    protected $description = 'Usuwa stare wpisy z dziennika aktywności (90 dni / 12 miesięcy dla działań admina)';

    /**
     * Usuwa przeterminowane wpisy.
     */
    public function handle(): int
    {
        $regular = Activity::query()
            ->where('log_name', '!=', AuditLogger::LOG_ADMIN)
            ->where('created_at', '<', now()->subDays(max(1, (int) $this->option('days'))))
            ->delete();

        $admin = Activity::query()
            ->where('log_name', AuditLogger::LOG_ADMIN)
            ->where('created_at', '<', now()->subDays(max(1, (int) $this->option('admin-days'))))
            ->delete();

        $this->info("Usunięto wpisów zwykłych: {$regular}, działań administratorów: {$admin}.");

        return self::SUCCESS;
    }
}
