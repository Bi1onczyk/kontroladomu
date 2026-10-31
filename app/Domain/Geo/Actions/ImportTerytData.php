<?php

namespace App\Domain\Geo\Actions;

use App\Domain\Geo\Data\TerytImportResult;
use App\Domain\Geo\Enums\LocalityKind;
use App\Domain\Geo\Exceptions\TerytImportException;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Geo\Support\SearchText;
use App\Domain\Geo\Support\TerytCsvReader;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import gmin (plik TERC) i miejscowości (plik SIMC) z rejestru TERYT GUS do bazy (ADR-012).
 *
 * Dane źródłowe pobiera ręcznie właściciel (eTeryt, "Pliki pełne", wersja podstawowa/urzędowa, CSV);
 * plików GUS nie commitujemy do repozytorium. Import jest bezpieczny do wielokrotnego uruchamiania:
 * `upsert` po kluczu biznesowym (`code`, `simc`), bez nadpisywania slugów i współrzędnych.
 *
 * Pamięć: plik SIMC jest czytany strumieniowo, w RAM jest jedna porcja wierszy oraz zbiór kodów SIMC
 * już widzianych w pliku (potrzebny do wyłączenia miejscowości, które zniknęły; ok. 100 tys. kluczy,
 * rząd kilkunastu MB). Miejscowości nieobecne w pliku dostają `is_active = false` (nie kasujemy ich:
 * mogą się do nich odwoływać nieruchomości), ale tylko gdy plik zawiera co najmniej 90% dotychczasowych
 * aktywnych miejscowości (zabezpieczenie przed obciętym plikiem).
 */
class ImportTerytData
{
    /** Minimalny udział wierszy pliku względem aktywnych miejscowości w bazie, przy którym wolno wyłączać brakujące. */
    private const MIN_FILE_COVERAGE = 0.9;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Importuje gminy i miejscowości z plików CSV.
     *
     * @param  string  $tercPath  Ścieżka do pliku TERC (CSV, UTF-8, separator średnik).
     * @param  string  $simcPath  Ścieżka do pliku SIMC (CSV, UTF-8, separator średnik).
     * @param  bool  $dryRun  Tylko policz, nic nie zapisuj.
     * @param  int  $chunkSize  Liczba miejscowości zapisywanych jednym zapytaniem i jedną transakcją.
     * @param  string|null  $resumeFrom  Kod SIMC, od którego wznowić (wiersze wcześniejsze w pliku są pomijane).
     * @param  int|null  $maxSeconds  Po przekroczeniu tego czasu import kończy się po bieżącej porcji (kod wznowienia w wyniku).
     * @param  (callable(string, int): void)|null  $onProgress  Wywoływane po każdej porcji: (etap, liczba przetworzonych wierszy).
     *
     * @throws TerytImportException Gdy plik jest nieprawidłowy albo wskazany kod wznowienia nie występuje w pliku.
     */
    public function handle(
        string $tercPath,
        string $simcPath,
        bool $dryRun = false,
        int $chunkSize = 1000,
        ?string $resumeFrom = null,
        ?int $maxSeconds = null,
        ?callable $onProgress = null,
    ): TerytImportResult {
        $chunkSize = max(1, $chunkSize);
        DB::connection()->disableQueryLog();

        // 1. Gminy (TERC): mały plik, ok. 4 tys. wierszy.
        $municipalities = $this->readMunicipalities($tercPath);
        [$munAdded, $munUpdated] = $this->saveMunicipalities($municipalities['rows'], $dryRun, $chunkSize);
        $onProgress && $onProgress('TERC', count($municipalities['rows']));

        $municipalityIds = $dryRun
            ? array_fill_keys(array_keys($municipalities['rows']), 0)
            : TerytMunicipality::query()->pluck('id', 'code')->all();

        // 2. Miejscowości (SIMC): strumieniowo, porcjami.
        $activeBefore = TerytLocality::query()->where('is_active', true)->count();
        $stats = $this->importLocalities(
            $simcPath, $municipalities['rows'], $municipalityIds, $dryRun, $chunkSize, $resumeFrom, $maxSeconds, $onProgress,
        );

        // 3. Wyłączenie miejscowości, które zniknęły z pliku (tylko po pełnym imporcie od początku pliku).
        $deactivated = 0;
        $deactivationSkipped = false;

        if ($stats['complete'] && $resumeFrom === null) {
            if ($activeBefore === 0 || $stats['seen_count'] >= self::MIN_FILE_COVERAGE * $activeBefore) {
                $deactivated = $this->deactivateMissing($stats['seen'], $dryRun);
            } else {
                $deactivationSkipped = true;
            }
        } else {
            $deactivationSkipped = true;
        }

        // 4. Daty stanu i źródło danych (tylko pełny, rzeczywisty import).
        if (! $dryRun && $stats['complete']) {
            $this->settings->put('teryt.terc_state_date', $municipalities['state_date']);
            $this->settings->put('teryt.simc_state_date', $stats['state_date']);
            $this->settings->put('teryt.imported_at', now()->toIso8601String());
            $this->settings->put('teryt.source', (string) __('geo.data_source'));
        }

        return new TerytImportResult(
            dryRun: $dryRun,
            municipalitiesAdded: $munAdded,
            municipalitiesUpdated: $munUpdated,
            localitiesAdded: $stats['added'],
            localitiesUpdated: $stats['updated'],
            deactivated: $deactivated,
            deactivationSkipped: $deactivationSkipped,
            skippedWithoutMunicipality: $stats['skipped'],
            complete: $stats['complete'],
            resumeFrom: $stats['resume_from'],
        );
    }

    /**
     * Czyta plik TERC i buduje wiersze gmin (klucz = kod 7-znakowy) z nazwami powiatu i województwa.
     *
     * @return array{rows: array<string, array<string, mixed>>, state_date: string}
     */
    private function readMunicipalities(string $path): array
    {
        $voivodeships = [];
        $counties = [];
        $municipalityRows = [];
        $stateDate = '';

        foreach (TerytCsvReader::rows($path, ['WOJ', 'POW', 'GMI', 'RODZ', 'NAZWA', 'STAN_NA']) as $row) {
            $stateDate = max($stateDate, $row['STAN_NA']);

            if ($row['POW'] === '') {
                $voivodeships[$row['WOJ']] = mb_strtolower($row['NAZWA']);
            } elseif ($row['GMI'] === '') {
                $counties[$row['WOJ'].$row['POW']] = $row['NAZWA'];
            } else {
                $municipalityRows[] = $row;
            }
        }

        $rows = [];

        foreach ($municipalityRows as $row) {
            $code = $row['WOJ'].$row['POW'].$row['GMI'].$row['RODZ'];
            $countyCode = $row['WOJ'].$row['POW'];

            if (! preg_match('/^\d{7}$/', $code) || ! isset($voivodeships[$row['WOJ']], $counties[$countyCode])) {
                throw new TerytImportException("Plik TERC jest niespójny: gmina {$code} bez powiatu lub województwa.");
            }

            $rows[$code] = [
                'code' => $code,
                'name' => $row['NAZWA'],
                'type_code' => $row['RODZ'],
                'county_code' => $countyCode,
                'county_name' => $counties[$countyCode],
                'county_name_search' => SearchText::normalize($counties[$countyCode]),
                'voivodeship_code' => $row['WOJ'],
                'voivodeship_name' => $voivodeships[$row['WOJ']],
                'name_search' => SearchText::normalize($row['NAZWA']),
            ];
        }

        if ($rows === []) {
            throw new TerytImportException("W pliku TERC ({$path}) nie znaleziono żadnej gminy.");
        }

        return ['rows' => $rows, 'state_date' => $stateDate];
    }

    /**
     * Zapisuje gminy (`upsert` po kodzie) w jednej transakcji.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array{0: int, 1: int} Liczba dodanych i zaktualizowanych.
     */
    private function saveMunicipalities(array $rows, bool $dryRun, int $chunkSize): array
    {
        $existing = array_flip(TerytMunicipality::query()->pluck('code')->all());
        $updated = count(array_intersect_key($rows, $existing));
        $added = count($rows) - $updated;

        if (! $dryRun) {
            // Tekst zamiast obiektu Carbon: obiekty Carbon w bindingach upsert powodowały rosnące zużycie pamięci (pomiar 30.10.2026).
            $now = now()->toDateTimeString();
            $payload = array_map(fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now], array_values($rows));

            DB::transaction(function () use ($payload, $chunkSize): void {
                foreach (array_chunk($payload, $chunkSize) as $chunk) {
                    TerytMunicipality::query()->upsert($chunk, ['code'], [
                        'name', 'type_code', 'county_code', 'county_name', 'county_name_search',
                        'voivodeship_code', 'voivodeship_name', 'name_search', 'updated_at',
                    ]);
                }
            });
        }

        return [$added, $updated];
    }

    /**
     * Czyta plik SIMC i zapisuje miejscowości porcjami.
     *
     * @param  array<string, array<string, mixed>>  $municipalities  Gminy z pliku TERC (po kodzie).
     * @param  array<string, int>  $municipalityIds  Kod gminy => id w bazie.
     * @return array{added: int, updated: int, skipped: int, complete: bool, resume_from: string|null, seen: array<string, true>, seen_count: int, state_date: string}
     */
    private function importLocalities(
        string $path,
        array $municipalities,
        array $municipalityIds,
        bool $dryRun,
        int $chunkSize,
        ?string $resumeFrom,
        ?int $maxSeconds,
        ?callable $onProgress,
    ): array {
        $startedAt = microtime(true);
        $columns = ['WOJ', 'POW', 'GMI', 'RODZ_GMI', 'RM', 'NAZWA', 'SYM', 'SYMPOD', 'STAN_NA'];

        $stats = [
            'added' => 0, 'updated' => 0, 'skipped' => 0, 'complete' => true, 'resume_from' => null,
            'seen' => [], 'seen_count' => 0, 'state_date' => '',
        ];
        $chunk = [];
        $processed = 0;
        $timeExceeded = false;
        $resuming = $resumeFrom !== null;

        foreach (TerytCsvReader::rows($path, $columns) as $lineNumber => $row) {
            if ($resuming) {
                if ($row['SYM'] !== $resumeFrom) {
                    continue;
                }
                $resuming = false;
            }

            // Poprzednia porcja przekroczyła limit czasu: ten wiersz jest punktem wznowienia.
            if ($timeExceeded) {
                $stats['complete'] = false;
                $stats['resume_from'] = $row['SYM'];
                break;
            }

            if (! preg_match('/^\d{7}$/', $row['SYM'])) {
                throw new TerytImportException("Linia {$lineNumber} pliku SIMC: nieprawidłowy identyfikator SYM.");
            }

            $this->assertValidLocalityRow($row, $lineNumber);

            $stats['state_date'] = max($stats['state_date'], $row['STAN_NA']);
            $stats['seen'][$row['SYM']] = true;
            $stats['seen_count']++;
            $processed++;

            $municipalityCode = $row['WOJ'].$row['POW'].$row['GMI'].$row['RODZ_GMI'];

            if (! isset($municipalityIds[$municipalityCode])) {
                $stats['skipped']++;

                continue;
            }

            $chunk[] = [$row, $municipalityIds[$municipalityCode], $municipalities[$municipalityCode]['name']];

            if (count($chunk) >= $chunkSize) {
                $this->saveLocalities($chunk, $dryRun, $stats);
                $chunk = [];
                $onProgress && $onProgress('SIMC', $processed);
                $timeExceeded = $maxSeconds !== null && (microtime(true) - $startedAt) >= $maxSeconds;
            }
        }

        if ($resuming) {
            throw new TerytImportException("Kod wznowienia {$resumeFrom} nie występuje w pliku SIMC.");
        }

        if ($chunk !== []) {
            $this->saveLocalities($chunk, $dryRun, $stats);
            $onProgress && $onProgress('SIMC', $processed);
        }

        if ($stats['seen_count'] === 0) {
            throw new TerytImportException("W pliku SIMC ({$path}) nie znaleziono żadnej miejscowości.");
        }

        return $stats;
    }

    /**
     * Sprawdza pola wiersza SIMC, zanim trafią do bazy (SYMPOD, RM, NAZWA, STAN_NA). Błędny wiersz przerywa import
     * czytelnym komunikatem z numerem linii (tak samo jak uszkodzony wiersz w {@see TerytCsvReader}).
     *
     * @param  array<string, string>  $row
     *
     * @throws TerytImportException
     */
    private function assertValidLocalityRow(array $row, int $lineNumber): void
    {
        $problem = match (true) {
            $row['SYMPOD'] !== '' && ! preg_match('/^\d{7}$/', $row['SYMPOD']) => 'nieprawidłowy identyfikator nadrzędny SYMPOD',
            ! preg_match('/^\d{2}$/', $row['RM']) => 'nieprawidłowy kod rodzaju RM',
            trim($row['NAZWA']) === '' || mb_strlen($row['NAZWA']) > 100 => 'pusta albo zbyt długa (ponad 100 znaków) nazwa',
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['STAN_NA']) => 'nieprawidłowa data STAN_NA (oczekiwany format RRRR-MM-DD)',
            default => null,
        };

        if ($problem !== null) {
            throw new TerytImportException("Linia {$lineNumber} pliku SIMC: {$problem}.");
        }
    }

    /**
     * Zapisuje jedną porcję miejscowości (`upsert` po SIMC) w transakcji i aktualizuje liczniki.
     * `upsert` NIE nadpisuje slugów, współrzędnych ani źródła współrzędnych.
     *
     * @param  list<array{0: array<string, string>, 1: int, 2: string}>  $chunk  Wiersz pliku, id gminy, nazwa gminy.
     * @param  array{added: int, updated: int, ...}  $stats
     */
    private function saveLocalities(array $chunk, bool $dryRun, array &$stats): void
    {
        $simcs = array_map(fn (array $item): string => $item[0]['SYM'], $chunk);
        $existingSlugs = TerytLocality::query()->whereIn('simc', $simcs)->pluck('slug', 'simc')->all();

        $newItems = array_values(array_filter($chunk, fn (array $item): bool => ! isset($existingSlugs[$item[0]['SYM']])));
        $newSlugs = $this->assignSlugs($newItems);

        $stats['updated'] += count($chunk) - count($newItems);
        $stats['added'] += count($newItems);

        if ($dryRun) {
            return;
        }

        // Tekst zamiast obiektu Carbon (patrz saveMunicipalities).
        $now = now()->toDateTimeString();
        $payload = [];

        foreach ($chunk as [$row, $municipalityId]) {
            $simc = $row['SYM'];
            $parent = ($row['SYMPOD'] === '' || $row['SYMPOD'] === $simc) ? null : $row['SYMPOD'];
            $kind = LocalityKind::fromSimcCode($row['RM']);

            $payload[] = [
                'simc' => $simc,
                'parent_simc' => $parent,
                'municipality_id' => $municipalityId,
                'name' => $row['NAZWA'],
                'name_search' => SearchText::normalize($row['NAZWA']),
                'kind_code' => $row['RM'],
                'kind' => $kind->value,
                'is_part' => $kind === LocalityKind::Part || $parent !== null,
                'slug' => $existingSlugs[$simc] ?? $newSlugs[$simc],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($payload): void {
            TerytLocality::query()->upsert($payload, ['simc'], [
                'parent_simc', 'municipality_id', 'name', 'name_search', 'kind_code', 'kind', 'is_part', 'is_active', 'updated_at',
            ]);
        });
    }

    /**
     * Nadaje unikalne slugi nowym miejscowościom: `dabrowka`, przy kolizji `dabrowka-gm-dopiewo`,
     * przy dalszej kolizji `dabrowka-0950123`. Slug jest generowany raz i nie zmienia się przy aktualizacji
     * (stabilne adresy SEO).
     *
     * @param  list<array{0: array<string, string>, 1: int, 2: string}>  $items
     * @return array<string, string> Kod SIMC => slug.
     */
    private function assignSlugs(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $candidates = [];

        foreach ($items as [$row, , $municipalityName]) {
            $base = mb_substr(Str::slug($row['NAZWA']), 0, 100);
            $base = $base !== '' ? $base : $row['SYM'];

            $candidates[$row['SYM']] = [
                $base,
                mb_substr($base.'-gm-'.Str::slug($municipalityName), 0, 150),
                $base.'-'.$row['SYM'],
            ];
        }

        $taken = array_flip(
            TerytLocality::query()->whereIn('slug', array_merge(...array_values($candidates)))->pluck('slug')->all()
        );
        $slugs = [];

        foreach ($candidates as $simc => $options) {
            $slug = collect($options)->first(fn (string $option): bool => ! isset($taken[$option]));

            // Ostateczny zapas: licznik (praktycznie nieosiągalny, bo ostatnia opcja zawiera SIMC).
            for ($i = 2; $slug === null; $i++) {
                $slug = isset($taken[$options[2].'-'.$i]) ? null : $options[2].'-'.$i;
            }

            $taken[$slug] = true;
            $slugs[$simc] = $slug;
        }

        return $slugs;
    }

    /**
     * Wyłącza aktywne miejscowości, których nie było w pliku. W trybie próbnym tylko je liczy.
     *
     * @param  array<string, true>  $seen  Kody SIMC obecne w pliku.
     */
    private function deactivateMissing(array $seen, bool $dryRun): int
    {
        $count = 0;

        TerytLocality::query()
            ->where('is_active', true)
            ->select(['id', 'simc'])
            ->chunkById(1000, function ($localities) use ($seen, $dryRun, &$count): void {
                $ids = $localities->filter(fn (TerytLocality $locality): bool => ! isset($seen[$locality->simc]))->pluck('id');

                if ($ids->isEmpty()) {
                    return;
                }

                $count += $ids->count();

                if (! $dryRun) {
                    TerytLocality::query()->whereIn('id', $ids->all())->update(['is_active' => false]);
                }
            });

        return $count;
    }
}
