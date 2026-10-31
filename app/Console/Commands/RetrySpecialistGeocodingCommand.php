<?php

namespace App\Console\Commands;

use App\Domain\Geo\Contracts\GeocodingProvider;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Jobs\GeocodeLocalityJob;
use App\Domain\Specialists\Models\SpecialistProfile;
use Illuminate\Console\Command;

/**
 * Ponawia ustalanie położenia miejscowości dla profili w stanie `pending` lub `failed` starszych niż godzina
 * (np. gdy dostawca geokodowania nie był skonfigurowany). Uruchamiane co godzinę z harmonogramu.
 *
 * Wrzuca do kolejki najwyżej `specialists.geocoding_retry_batch` (20) różnych miejscowości na uruchomienie;
 * unikalność zadań (`ShouldBeUnique`) chroni przed dublami. Bez skonfigurowanego dostawcy komenda nic nie robi.
 */
class RetrySpecialistGeocodingCommand extends Command
{
    protected $signature = 'specialists:retry-geocoding';

    protected $description = 'Ponawia ustalanie położenia miejscowości dla profili fachowców bez współrzędnych';

    /**
     * Zlecza zadania geokodowania w kolejce.
     */
    public function handle(GeocodingProvider $provider): int
    {
        if (! $provider->isConfigured()) {
            $this->info('Geokodowanie nie jest skonfigurowane (NOMINATIM_CONTACT_EMAIL); pominięto.');

            return self::SUCCESS;
        }

        $simcs = SpecialistProfile::query()
            ->whereIn('location_status', [LocationStatus::Pending->value, LocationStatus::Failed->value])
            ->whereNotNull('locality_simc')
            ->where('updated_at', '<=', now()->subHour())
            ->select('locality_simc')
            ->groupBy('locality_simc')
            ->orderBy('locality_simc')
            ->limit((int) config('specialists.geocoding_retry_batch'))
            ->pluck('locality_simc');

        foreach ($simcs as $simc) {
            GeocodeLocalityJob::dispatch((string) $simc);
        }

        $this->info("Zlecono geokodowanie miejscowości: {$simcs->count()}.");

        return self::SUCCESS;
    }
}
