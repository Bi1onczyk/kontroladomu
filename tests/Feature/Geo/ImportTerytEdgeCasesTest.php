<?php

/*
| Przypadki brzegowe importu TERYT (tester, zadanie 003): uszkodzone wiersze, wznowienie, powtórny import,
| wydajność pamięciowa. Pliki SYNTETYCZNE w formacie GUS (BOM, CRLF, średniki). Uzupełnia ImportTerytTest.
*/

use App\Domain\Geo\Actions\ImportTerytData;
use App\Domain\Geo\Data\TerytImportResult;
use App\Domain\Geo\Exceptions\TerytImportException;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;

const EDGE_SIMC_HEADER = "WOJ;POW;GMI;RODZ_GMI;RM;MZ;NAZWA;SYM;SYMPOD;STAN_NA\r\n";

/** Zapisuje plik tymczasowy (usuwany po teście przez system) i zwraca ścieżkę. */
function edgeFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'teryt-edge');
    file_put_contents($path, $content);

    return $path;
}

/** Wiersz SIMC dla gminy Dopiewo (30;64;03;2). */
function edgeSimcRow(string $sym, string $name = 'Testowo', string $rm = '01', ?string $sympod = null): string
{
    return "30;64;03;2;{$rm};1;{$name};{$sym};".($sympod ?? $sym).";2026-01-01\r\n";
}

function edgeTerc(): string
{
    return base_path('tests/Fixtures/teryt/TERC.csv');
}

function edgeSimc(): string
{
    return base_path('tests/Fixtures/teryt/SIMC.csv');
}

function edgeImport(string $simc, array $options = []): TerytImportResult
{
    return app(ImportTerytData::class)->handle(
        edgeTerc(),
        $simc,
        dryRun: $options['dryRun'] ?? false,
        chunkSize: $options['chunk'] ?? 1000,
        resumeFrom: $options['resumeFrom'] ?? null,
        maxSeconds: $options['maxSeconds'] ?? null,
    );
}

it('nie zmienia created_at ani identyfikatorów przy ponownym imporcie tego samego pliku', function () {
    edgeImport(edgeSimc());
    $idsBefore = TerytLocality::query()->orderBy('simc')->pluck('id', 'simc')->all();
    $munIdsBefore = TerytMunicipality::query()->orderBy('code')->pluck('id', 'code')->all();
    TerytLocality::query()->update(['created_at' => '2020-01-01 00:00:00']);

    edgeImport(edgeSimc());

    expect(TerytLocality::query()->orderBy('simc')->pluck('id', 'simc')->all())->toBe($idsBefore)
        ->and(TerytMunicipality::query()->orderBy('code')->pluck('id', 'code')->all())->toBe($munIdsBefore)
        ->and(TerytLocality::query()->where('created_at', '!=', '2020-01-01 00:00:00')->count())->toBe(0);
});

it('przy ponownym imporcie aktualizuje kolumny wyszukiwania, gdy zmieniła się nazwa gminy lub powiatu', function () {
    edgeImport(edgeSimc());

    $changedTerc = edgeFile(str_replace(
        ['Dopiewo;gmina wiejska', 'poznański;powiat'],
        ['Zażółć;gmina wiejska', 'wrzesiński;powiat'],
        file_get_contents(edgeTerc())
    ));
    app(ImportTerytData::class)->handle($changedTerc, edgeSimc());

    $municipality = TerytMunicipality::query()->where('code', '3064032')->firstOrFail();
    expect($municipality->name)->toBe('Zażółć')
        ->and($municipality->name_search)->toBe('zazolc')
        ->and($municipality->county_name_search)->toBe('wrzesinski');

    // Wyszukiwarka od razu widzi nową nazwę gminy.
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka zazolc'))->assertOk()->assertJsonCount(3, 'data');
});

it('import w trybie próbnym liczy miejscowości do wyłączenia, ale ich nie wyłącza', function () {
    edgeImport(edgeSimc());
    TerytLocality::factory()->count(2)->create(['municipality_id' => TerytMunicipality::query()->value('id')]);

    $result = edgeImport(edgeSimc(), ['dryRun' => true]);

    expect($result->deactivated)->toBe(2)
        ->and(TerytLocality::query()->where('is_active', false)->count())->toBe(0);
});

it('wznowienie od ostatniej porcji kończy import i daje ten sam stan co import jednorazowy', function () {
    $full = edgeFile(file_get_contents(edgeSimc()));
    edgeImport($full);
    $expected = TerytLocality::query()->orderBy('simc')->get(['simc', 'name', 'slug', 'is_part'])->toArray();
    TerytLocality::query()->delete();

    $first = edgeImport($full, ['chunk' => 10, 'maxSeconds' => 0]);
    expect($first->complete)->toBeFalse()->and($first->resumeFrom)->toBe('0900011');

    $second = edgeImport($full, ['chunk' => 10, 'resumeFrom' => $first->resumeFrom]);
    expect($second->complete)->toBeTrue();

    $actual = TerytLocality::query()->orderBy('simc')->get(['simc', 'name', 'slug', 'is_part'])->toArray();
    // Slugi mogą się różnić wyłącznie kolejnością nadawania przy kolizjach; sprawdzamy zgodność kodów, nazw i części.
    expect(array_column($actual, 'simc'))->toBe(array_column($expected, 'simc'))
        ->and(array_column($actual, 'name'))->toBe(array_column($expected, 'name'))
        ->and(array_column($actual, 'is_part'))->toBe(array_column($expected, 'is_part'))
        ->and(collect($actual)->pluck('slug')->unique())->toHaveCount(count($actual));
});

it('wznowienie z kodu będącego w ostatniej porcji nie gubi wierszy i nie dubluje ich', function () {
    $first = edgeImport(edgeSimc(), ['chunk' => 10, 'maxSeconds' => 0]);
    edgeImport(edgeSimc(), ['chunk' => 10, 'resumeFrom' => $first->resumeFrom]);
    // Ponowne wznowienie od tego samego kodu (np. operator powtórzył polecenie) jest bezpieczne.
    edgeImport(edgeSimc(), ['chunk' => 10, 'resumeFrom' => $first->resumeFrom]);

    expect(TerytLocality::query()->count())->toBe(24)
        ->and(TerytLocality::query()->distinct()->count('simc'))->toBe(24);
});

it('wznowienie nie wyłącza miejscowości spoza zakresu (import częściowy)', function () {
    edgeImport(edgeSimc());
    $result = edgeImport(edgeSimc(), ['resumeFrom' => '0900020']);

    expect($result->deactivationSkipped)->toBeTrue()
        ->and(TerytLocality::query()->where('is_active', false)->count())->toBe(0);
});

it('komenda wypisuje gotowe polecenie wznowienia po przekroczeniu limitu czasu', function () {
    $this->artisan('teryt:import', ['--terc' => edgeTerc(), '--simc' => edgeSimc(), '--chunk' => 5, '--max-seconds' => 0])
        ->expectsOutputToContain('--resume-from=0900006')
        ->assertSuccessful();

    $this->artisan('teryt:import', ['--terc' => edgeTerc(), '--simc' => edgeSimc(), '--chunk' => 5, '--resume-from' => '0900006'])
        ->expectsOutputToContain('Import zakończony.')
        ->assertSuccessful();

    expect(TerytLocality::query()->count())->toBe(24);
});

it('komenda zgłasza nieistniejący kod wznowienia jako błąd, nie jako wyjątek ze śladem stosu', function () {
    $this->artisan('teryt:import', ['--terc' => edgeTerc(), '--simc' => edgeSimc(), '--resume-from' => '1234567'])
        ->expectsOutputToContain('Kod wznowienia 1234567')
        ->assertFailed();
});

it('odrzuca wiersze SIMC z niepoprawnym identyfikatorem SYM i podaje numer linii', function (string $sym) {
    $file = edgeFile("\xEF\xBB\xBF".EDGE_SIMC_HEADER.edgeSimcRow('0900001').edgeSimcRow($sym));

    try {
        edgeImport($file);
        $this->fail('Oczekiwano wyjątku TerytImportException.');
    } catch (TerytImportException $e) {
        expect($e->getMessage())->toContain('Linia 3')->toContain('SYM');
    }
})->with([
    'pusty' => [''],
    'za krótki' => ['12345'],
    'za długi' => ['12345678'],
    'litery' => ['ABC1234'],
    'ze spacją' => ['090 001'],
]);

it('po błędzie w środku pliku nie zapisuje dat stanu ani znacznika zakończenia importu', function () {
    $rows = '';
    foreach (range(1, 12) as $i) {
        $rows .= edgeSimcRow(sprintf('09100%02d', $i), "Wieś {$i}");
    }
    $file = edgeFile("\xEF\xBB\xBF".EDGE_SIMC_HEADER.$rows."30;64;03;2;01;1;Zepsuta\r\n");

    expect(fn () => edgeImport($file, ['chunk' => 5]))->toThrow(TerytImportException::class, 'ma 7 pól zamiast 10');

    $settings = app(SettingsRepository::class);
    expect($settings->get('teryt.imported_at'))->toBeNull()
        ->and($settings->get('teryt.simc_state_date'))->toBeNull()
        // Pierwsze pełne porcje zostały zatwierdzone (upsert jest idempotentny, więc to bezpieczne przy ponowieniu).
        ->and(TerytLocality::query()->count())->toBeLessThanOrEqual(12);
});

it('pomija puste linie w środku i na końcu pliku oraz akceptuje końce linii LF i brak końcowego newline', function () {
    $content = EDGE_SIMC_HEADER
        .edgeSimcRow('0910001', 'Pierwsza')
        ."\r\n\r\n"
        .rtrim(edgeSimcRow('0910002', 'Druga'), "\r\n");
    $file = edgeFile(str_replace("\r\n", "\n", $content));

    $result = edgeImport($file);

    expect($result->localitiesAdded)->toBe(2);
});

it('ignoruje nieznane kolumny (także w środku) i dowolną kolejność kolumn', function () {
    $header = "STAN_NA;SYMPOD;SYM;NOWA_KOLUMNA;NAZWA;RM;RODZ_GMI;GMI;POW;WOJ\r\n";
    $row = "2026-01-01;0910001;0910001;cokolwiek;Przestawiona;01;2;03;64;30\r\n";

    $result = edgeImport(edgeFile($header.$row));

    expect($result->localitiesAdded)->toBe(1)
        ->and(TerytLocality::query()->where('simc', '0910001')->value('name'))->toBe('Przestawiona');
});

it('dodatkowe pola na końcu wiersza są ignorowane, a nie powodują błędu', function () {
    $result = edgeImport(edgeFile(EDGE_SIMC_HEADER.rtrim(edgeSimcRow('0910001'), "\r\n").";dodatkowe;pola\r\n"));

    expect($result->localitiesAdded)->toBe(1);
});

it('duplikat kodu SIMC w pliku nie powoduje błędu ani podwójnego wiersza w bazie', function () {
    $file = edgeFile(EDGE_SIMC_HEADER.edgeSimcRow('0910001', 'Dublet').edgeSimcRow('0910001', 'Dublet'));

    edgeImport($file);

    expect(TerytLocality::query()->where('simc', '0910001')->count())->toBe(1);
});

it('odrzuca plik pusty, sam nagłówek, XML zamiast CSV i separator przecinek', function (string $content, string $message) {
    expect(fn () => edgeImport(edgeFile($content)))->toThrow(TerytImportException::class, $message);
})->with([
    'pusty plik' => ['', 'Plik jest pusty'],
    'sam nagłówek' => [EDGE_SIMC_HEADER, 'nie znaleziono żadnej miejscowości'],
    'XML' => ['<?xml version="1.0" encoding="UTF-8"?><teryt><row/></teryt>', 'brakuje kolumn'],
    'przecinki' => ["WOJ,POW,GMI,RODZ_GMI,RM,MZ,NAZWA,SYM,SYMPOD,STAN_NA\r\n30,64,03,2,01,1,X,0910001,0910001,2026-01-01\r\n", 'brakuje kolumn'],
]);

it('odrzuca wiersz SIMC w kodowaniu Windows-1250 i wskazuje numer linii', function () {
    $file = edgeFile(EDGE_SIMC_HEADER.edgeSimcRow('0910001').str_replace('Testowo', "Za\xBF\xF3\xB3\xE6", edgeSimcRow('0910002')));

    expect(fn () => edgeImport($file))->toThrow(TerytImportException::class, 'Linia 3');
});

it('odrzuca linię złożoną z samych spacji jako uszkodzony wiersz', function () {
    $file = edgeFile(EDGE_SIMC_HEADER.edgeSimcRow('0910001')."   \r\n");

    expect(fn () => edgeImport($file))->toThrow(TerytImportException::class, 'ma 1 pól');
});

it('plik TERC bez gmin lub z gminą bez powiatu kończy się czytelnym błędem', function () {
    $onlyVoivodeship = edgeFile("WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA\r\n30;;;;WIELKOPOLSKIE;województwo;2026-01-01\r\n");
    $orphan = edgeFile("WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA\r\n30;;;;WIELKOPOLSKIE;województwo;2026-01-01\r\n30;64;03;2;Dopiewo;gmina;2026-01-01\r\n");

    expect(fn () => app(ImportTerytData::class)->handle($onlyVoivodeship, edgeSimc()))
        ->toThrow(TerytImportException::class, 'nie znaleziono żadnej gminy');
    expect(fn () => app(ImportTerytData::class)->handle($orphan, edgeSimc()))
        ->toThrow(TerytImportException::class, 'niespójny');
});

it('nieudany import nie zmienia istniejących danych gmin (TERC jest czytany w całości przed zapisem)', function () {
    edgeImport(edgeSimc());
    $before = TerytMunicipality::query()->orderBy('code')->get(['code', 'name', 'updated_at'])->toArray();

    $broken = edgeFile("WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA\r\n30;;;;WIELKOPOLSKIE;województwo;2026-01-01\r\n30;64;03;2;Dopiewo;gmina;2026-01-01\r\n");
    expect(fn () => app(ImportTerytData::class)->handle($broken, edgeSimc()))->toThrow(TerytImportException::class);

    expect(TerytMunicipality::query()->orderBy('code')->get(['code', 'name', 'updated_at'])->toArray())->toBe($before);
});

it('miejscowość, której nazwa składa się z samych znaków specjalnych, dostaje slug z kodu SIMC', function () {
    edgeImport(edgeFile(EDGE_SIMC_HEADER.edgeSimcRow('0910001', '"""')));

    expect(TerytLocality::query()->where('simc', '0910001')->value('slug'))->toBe('0910001');
});

it('nadaje różne slugi dwóm nowym miejscowościom o tej samej nazwie i gminie w jednej porcji', function () {
    edgeImport(edgeFile(EDGE_SIMC_HEADER.edgeSimcRow('0910001', 'Lipa').edgeSimcRow('0910002', 'Lipa').edgeSimcRow('0910003', 'Lipa')));

    $slugs = TerytLocality::query()->where('name', 'Lipa')->pluck('slug')->all();

    expect($slugs)->toHaveCount(3)->and(array_unique($slugs))->toHaveCount(3);
});

it('importuje 20 tysięcy miejscowości w stałej pamięci i w rozsądnym czasie', function () {
    $rows = '';
    foreach (range(1, 20000) as $i) {
        $rows .= edgeSimcRow((string) (8000000 + $i), "Wieś {$i}");
    }
    $file = edgeFile("\xEF\xBB\xBF".EDGE_SIMC_HEADER.$rows);
    unset($rows);

    gc_collect_cycles();
    $memoryBefore = memory_get_usage(true);
    $startedAt = microtime(true);

    $result = edgeImport($file);

    $seconds = microtime(true) - $startedAt;
    $growthMb = (memory_get_peak_usage(true) - $memoryBefore) / 1048576;

    expect($result->localitiesAdded)->toBe(20000)
        ->and(TerytLocality::query()->count())->toBe(20000)
        ->and($seconds)->toBeLessThan(30)
        // Pamięć nie rośnie liniowo z liczbą wierszy (szczyt rzędu kilkunastu MB na zbiór widzianych kodów + porcja).
        ->and($growthMb)->toBeLessThan(40);
});

it('komenda odrzuca brak obu plików i nieistniejącą ścieżkę względną', function () {
    $this->artisan('teryt:import')->assertExitCode(2);
    $this->artisan('teryt:import', ['--terc' => 'teryt-import/NIE_MA.csv', '--simc' => 'teryt-import/NIE_MA.csv'])->assertExitCode(2);
});

it('import nie zostawia otwartej transakcji w bazie po błędzie', function () {
    $file = edgeFile(EDGE_SIMC_HEADER.edgeSimcRow('0910001')."zepsuty\r\n");
    $levelBefore = DB::transactionLevel();

    expect(fn () => edgeImport($file))->toThrow(TerytImportException::class);

    expect(DB::transactionLevel())->toBe($levelBefore);
});
