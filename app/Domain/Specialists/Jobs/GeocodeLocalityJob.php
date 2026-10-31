<?php

namespace App\Domain\Specialists\Jobs;

use App\Domain\Geo\Actions\ResolveLocalityCoordinates;
use App\Domain\Geo\Contracts\GeocodingProvider;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Actions\ApplyLocalityPosition;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Models\SpecialistProfile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Ustala w kolejce współrzędne miejscowości startowej fachowców (ADR-020, warunek audytu 003 #5:
 * żadnego geokodowania w żądaniu webowym).
 *
 * Unikalność po kodzie SIMC (`ShouldBeUnique`, 3600 s) chroni przed lawiną zadań dla tej samej miejscowości.
 * Brak skonfigurowanego dostawcy (pusty `NOMINATIM_CONTACT_EMAIL`) nie jest błędem: zadanie kończy się cicho,
 * profile zostają `pending`, a godzinna komenda `specialists:retry-geocoding` wróci do nich później.
 * Wynik "nie znaleziono" lub niedostępność usługi kończy się wyjątkiem, żeby zadziałało opóźnienie między próbami;
 * po wyczerpaniu prób profile dostają stan `failed`.
 */
class GeocodeLocalityJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** Liczba prób. */
    public int $tries = 3;

    /** Limit czasu jednej próby (s). */
    public int $timeout = 30;

    /**
     * Czas blokady unikalności (s). Musi być co najmniej tak długi jak suma opóźnień `backoff()` (2520 s) plus czasy
     * prób, żeby blokada nie wygasła między ponowieniami i nie powstały duplikaty zadania (audyt 004).
     */
    public int $uniqueFor = 3600;

    /**
     * @param  string  $simc  Kod TERYT miejscowości (jedyna dana w kolejce; brak danych osobowych).
     */
    public function __construct(public readonly string $simc) {}

    /**
     * Klucz unikalności: kod miejscowości.
     */
    public function uniqueId(): string
    {
        return $this->simc;
    }

    /**
     * Opóźnienia między próbami (s): 2 min, 10 min, 30 min.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [120, 600, 1800];
    }

    /**
     * Ustala pozycję miejscowości i uzupełnia nią profile.
     *
     * @throws RuntimeException Gdy pozycji nie udało się ustalić (uruchamia ponowienie).
     */
    public function handle(GeocodingProvider $provider, ResolveLocalityCoordinates $resolve, ApplyLocalityPosition $apply): void
    {
        $locality = TerytLocality::query()->with('municipality')->where('simc', $this->simc)->where('is_active', true)->first();

        if ($locality === null) {
            return;
        }

        if ($locality->hasCoordinates()) {
            $apply->handle($locality);

            return;
        }

        // Brak konfiguracji dostawcy: bez wyjątku i bez ponowień (profil zostaje "pending").
        if (! $provider->isConfigured()) {
            return;
        }

        if ($resolve->handle($locality) === null) {
            throw new RuntimeException('Nie udało się ustalić współrzędnych miejscowości.');
        }

        $apply->handle($locality->refresh());
    }

    /**
     * Po wyczerpaniu prób oznacza oczekujące profile tej miejscowości jako nieudane (UI pokaże przycisk ponowienia).
     */
    public function failed(?Throwable $exception = null): void
    {
        SpecialistProfile::query()
            ->where('locality_simc', $this->simc)
            ->where('location_status', LocationStatus::Pending->value)
            ->update(['location_status' => LocationStatus::Failed->value]);
    }
}
