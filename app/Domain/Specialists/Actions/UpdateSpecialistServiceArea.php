<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Specialists\Enums\LocationStatus;
use App\Domain\Specialists\Jobs\GeocodeLocalityJob;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Zapis sekcji "Obszar działania i dojazd": miejscowość startowa (TERYT), promień, stawka za km i km gratis (S8).
 *
 * Zmiana miejscowości zeruje punkt startowy i ustawia `pending`. Gdy miejscowość ma już współrzędne w TERYT,
 * są kopiowane od razu (zero sieci), inaczej do kolejki trafia `GeocodeLocalityJob` (po zatwierdzeniu transakcji).
 * Geokodowanie NIGDY nie odbywa się w żądaniu webowym (audyt 003 #5). Zmiana samego promienia lub dojazdu nie
 * rusza punktu startowego. Przy stawce 0 km gratis są zerowane (nie mają sensu bez stawki).
 */
class UpdateSpecialistServiceArea
{
    public function __construct(private readonly ApplyLocalityPosition $applyPosition) {}

    /**
     * @param  array{locality_simc: string, service_radius_km: int, travel_rate_per_km_grosze: int, travel_free_km: int}  $data  Dane po walidacji.
     */
    public function handle(SpecialistProfile $profile, array $data, User $actor): SpecialistProfile
    {
        $localityChanged = $profile->locality_simc !== $data['locality_simc'];
        $dispatchSimc = null;

        if ($localityChanged) {
            $this->enforceDailyLocalityLimit($actor);
        }

        DB::transaction(function () use ($profile, $data, $actor, $localityChanged, &$dispatchSimc): void {
            $rate = (int) $data['travel_rate_per_km_grosze'];

            $profile->fill([
                'service_radius_km' => (int) $data['service_radius_km'],
                'travel_rate_per_km_grosze' => $rate,
                'travel_free_km' => $rate === 0 ? 0 : min((int) $data['travel_free_km'], (int) $data['service_radius_km']),
            ]);

            if ($localityChanged) {
                $profile->forceFill([
                    'locality_simc' => $data['locality_simc'],
                    'start_lat' => null,
                    'start_lng' => null,
                    'start_precision' => null,
                    'start_source' => null,
                    'location_status' => LocationStatus::Pending,
                    'location_resolved_at' => null,
                ]);
            }

            $profile->save();

            if ($localityChanged) {
                $locality = TerytLocality::query()->where('simc', $data['locality_simc'])->firstOrFail();

                if ($locality->hasCoordinates()) {
                    $this->applyPosition->handle($locality);
                    $profile->refresh();
                } else {
                    $dispatchSimc = $locality->simc;
                }
            }

            AuditLogger::record('service_area_updated', $actor, $profile, [
                'locality_changed' => $localityChanged,
                'radius_km' => $profile->service_radius_km,
            ], AuditLogger::LOG_SPECIALIST);
        });

        if ($dispatchSimc !== null) {
            $this->dispatchGeocoding($dispatchSimc);
        }

        return $profile;
    }

    /**
     * Dzienny limit zmian miejscowości na konto (audyt 004 #3): każda nowa miejscowość bez współrzędnych to zapytanie
     * do publicznego Nominatim, więc przełączanie w pętli mogłoby zapchać kolejkę i skończyć blokadą IP serwera.
     * Zmiana jest liczona w chwili sprawdzenia (okno 24 godzin); po przekroczeniu limitu zwracamy błąd pola.
     *
     * @throws ValidationException Gdy konto wyczerpało dzienny limit zmian miejscowości.
     */
    private function enforceDailyLocalityLimit(User $actor): void
    {
        $max = (int) config('specialists.locality_changes_per_day');

        $allowed = RateLimiter::attempt('locality-change:'.$actor->id, $max, static fn (): bool => true, 86400);

        if ($allowed === false) {
            throw ValidationException::withMessages([
                'locality_simc' => __('specialist.validation.locality_change_limited', ['max' => $max]),
            ]);
        }
    }

    /**
     * Wrzuca zadanie do kolejki. Błąd kolejki nie może przewrócić już zapisanego profilu:
     * godzinna komenda `specialists:retry-geocoding` i tak wróci do profilu w stanie `pending`.
     */
    private function dispatchGeocoding(string $simc): void
    {
        try {
            GeocodeLocalityJob::dispatch($simc);
        } catch (Throwable $e) {
            Log::warning('Nie udało się zlecić geokodowania miejscowości.', ['exception' => $e::class, 'simc' => $simc]);
        }
    }
}
