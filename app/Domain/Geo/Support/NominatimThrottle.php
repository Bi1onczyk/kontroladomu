<?php

namespace App\Domain\Geo\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Limit zapytań do Nominatim: najwyżej jedno na `min_interval_ms` (domyślnie 1,1 s) dla CAŁEJ aplikacji,
 * czyli łącznie dla wszystkich użytkowników i procesów PHP (polityka OSMF: 1 zapytanie/s).
 *
 * Mechanizm: blokada `Cache::lock('nominatim')` (przy cache w bazie: tabela `cache_locks`, działa między procesami
 * bez Redisa) oraz zapis czasu ostatniego zapytania w cache. W sekcji krytycznej czekamy do upływu odstępu,
 * wykonujemy zapytanie i zapisujemy czas. Gdy blokady nie uda się zdobyć w kilka sekund, zapytanie NIE jest
 * wykonywane (żądanie webowe nie może wisieć).
 */
class NominatimThrottle
{
    private const LOCK_NAME = 'nominatim';

    private const LAST_AT_KEY = 'nominatim:last_at';

    public function __construct(private readonly Sleeper $sleeper) {}

    /**
     * Wykonuje `$callback` z zachowaniem odstępu od poprzedniego zapytania.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T|null null, gdy nie udało się zdobyć blokady (zapytanie nie zostało wykonane)
     */
    public function run(Closure $callback): mixed
    {
        $lock = Cache::lock(self::LOCK_NAME, (int) config('geocoding.lock_seconds'));

        try {
            $lock->block((int) config('geocoding.lock_wait_seconds'));
        } catch (LockTimeoutException) {
            return null;
        }

        try {
            $lastAt = Cache::get(self::LAST_AT_KEY);

            if (is_float($lastAt) || is_int($lastAt)) {
                $waitMs = (int) ceil((int) config('geocoding.min_interval_ms') - ($this->sleeper->now() - $lastAt) * 1000);

                if ($waitMs > 0) {
                    $this->sleeper->sleepMilliseconds($waitMs);
                }
            }

            return $callback();
        } finally {
            // Czas zapisujemy także po błędzie: nieudane zapytanie też obciąża limit usługi.
            Cache::put(self::LAST_AT_KEY, $this->sleeper->now(), 3600);
            $lock->release();
        }
    }
}
