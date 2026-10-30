<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Usuwa konta, których adres e-mail nie został potwierdzony przez 7 dni.
 *
 * Powód: minimalizacja danych (RODO) oraz odblokowanie adresu, który ktoś zarejestrował
 * cudzym kosztem (pre-hijacking). Uruchamiane codziennie z harmonogramu.
 */
class PruneUnverifiedAccounts extends Command
{
    protected $signature = 'accounts:prune-unverified {--days=7 : Po ilu dniach usunąć niepotwierdzone konto}';

    protected $description = 'Usuwa konta z niepotwierdzonym adresem e-mail starsze niż podana liczba dni';

    /**
     * Wykonuje czyszczenie i wypisuje liczbę usuniętych kont.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = 0;

        User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays($days))
            ->each(function (User $user) use (&$deleted) {
                $user->delete();
                $deleted++;
            });

        $this->info("Usunięto niepotwierdzonych kont: {$deleted}.");

        return self::SUCCESS;
    }
}
