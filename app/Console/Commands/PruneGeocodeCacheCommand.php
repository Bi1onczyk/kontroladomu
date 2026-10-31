<?php

namespace App\Console\Commands;

use App\Domain\Geo\Models\GeocodeCacheEntry;
use Illuminate\Console\Command;

/**
 * Usuwa wygasłe wpisy z cache geokodowania (`geocode_cache`). Uruchamiane codziennie z harmonogramu.
 */
class PruneGeocodeCacheCommand extends Command
{
    protected $signature = 'geocode:prune';

    protected $description = 'Usuwa wygasłe wpisy z cache geokodowania';

    /**
     * Kasuje wpisy, których termin ważności minął.
     */
    public function handle(): int
    {
        $deleted = GeocodeCacheEntry::query()->where('expires_at', '<', now())->delete();

        $this->info("Usunięto wygasłych wpisów geokodowania: {$deleted}.");

        return self::SUCCESS;
    }
}
