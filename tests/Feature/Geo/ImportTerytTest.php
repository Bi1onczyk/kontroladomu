<?php

/*
| Testy importu TERYT na SYNTETYCZNYCH plikach w formacie GUS (BOM, CRLF, średniki).
| Importer był też uruchomiony ręcznie na prawdziwych plikach GUS z 30.10.2026 (wynik w zadaniu 003).
*/

use App\Domain\Geo\Actions\ImportTerytData;
use App\Domain\Geo\Enums\LocalityKind;
use App\Domain\Geo\Exceptions\TerytImportException;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Settings\SettingsRepository;

function tercFixture(): string
{
    return base_path('tests/Fixtures/teryt/TERC.csv');
}

function simcFixture(): string
{
    return base_path('tests/Fixtures/teryt/SIMC.csv');
}

/** Zapisuje plik tymczasowy i zwraca jego ścieżkę. */
function tempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'teryt');
    file_put_contents($path, $content);

    return $path;
}

it('importuje gminy z nazwami powiatu i województwa', function () {
    $result = app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    expect($result->municipalitiesAdded)->toBe(7)
        ->and(TerytMunicipality::query()->count())->toBe(7);

    $dopiewo = TerytMunicipality::query()->where('code', '3064032')->firstOrFail();
    expect($dopiewo->name)->toBe('Dopiewo')
        ->and($dopiewo->county_name)->toBe('poznański')
        ->and($dopiewo->county_name_search)->toBe('poznanski')
        ->and($dopiewo->voivodeship_name)->toBe('wielkopolskie')
        ->and($dopiewo->county_code)->toBe('3064')
        ->and($dopiewo->name_search)->toBe('dopiewo');

    // Gmina miejsko-wiejska ma trzy wiersze (3, 4, 5), a SIMC odwołuje się do 4 i 5.
    expect(TerytMunicipality::query()->where('name', 'Kórnik')->count())->toBe(3);
});

it('importuje miejscowości: rodzaje, części, nazwy i cudzysłów w nazwie', function () {
    $result = app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    expect($result->localitiesAdded)->toBe(24)
        ->and($result->localitiesUpdated)->toBe(0)
        ->and($result->skippedWithoutMunicipality)->toBe(0)
        ->and($result->complete)->toBeTrue();

    $part = TerytLocality::query()->where('simc', '0900005')->firstOrFail();
    expect($part->kind)->toBe(LocalityKind::Part)
        ->and($part->is_part)->toBeTrue()
        ->and($part->parent_simc)->toBe('0900001');

    $village = TerytLocality::query()->where('simc', '0900002')->firstOrFail();
    expect($village->kind)->toBe(LocalityKind::Village)
        ->and($village->is_part)->toBeFalse()
        ->and($village->parent_simc)->toBeNull()
        ->and($village->is_active)->toBeTrue()
        ->and($village->lat)->toBeNull();

    // Część miasta (RM 99).
    expect(TerytLocality::query()->where('simc', '0900011')->value('is_part'))->toBeTrue();
    expect(TerytLocality::query()->where('simc', '0900006')->firstOrFail()->kind)->toBe(LocalityKind::City);

    // Nazwa z cudzysłowem nie jest zniekształcona (czytnik nie używa fgetcsv).
    expect(TerytLocality::query()->where('simc', '0900022')->value('name'))->toBe('Mazewo Dworskie"B"');
    expect(TerytLocality::query()->where('simc', '0900019')->value('name_search'))->toBe('laczki zagorze');
});

it('nadaje unikalne slugi dla Dąbrówek: nazwa, gmina, kod SIMC', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    $slugs = TerytLocality::query()->where('name', 'Dąbrówka')->orderBy('simc')->pluck('slug', 'simc')->all();

    expect($slugs)->toBe([
        '0900001' => 'dabrowka',
        '0900007' => 'dabrowka-gm-kornik',
        '0900013' => 'dabrowka-gm-czernichow',
        '0900016' => 'dabrowka-gm-liszki',
        '0900017' => 'dabrowka-gm-dopiewo',
        '0900018' => 'dabrowka-0900018',
    ]);
});

it('zapisuje daty stanu i źródło danych w ustawieniach', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());
    $settings = app(SettingsRepository::class);

    expect($settings->get('teryt.simc_state_date'))->toBe('2026-01-01')
        ->and($settings->get('teryt.terc_state_date'))->toBe('2026-01-01')
        ->and($settings->get('teryt.source'))->toBe('GUS, rejestr TERYT')
        ->and($settings->get('teryt.imported_at'))->not->toBeNull();
});

it('jest idempotentny i nie nadpisuje slugów ani współrzędnych', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    TerytLocality::query()->where('simc', '0900002')->update([
        'lat' => 52.1234567, 'lng' => 16.7654321, 'coordinates_source' => 'manual', 'slug' => 'moj-slug',
    ]);
    TerytLocality::query()->where('simc', '0900003')->update(['name' => 'Stara nazwa']);

    $result = app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    expect($result->localitiesAdded)->toBe(0)
        ->and($result->localitiesUpdated)->toBe(24)
        ->and($result->municipalitiesAdded)->toBe(0)
        ->and(TerytLocality::query()->count())->toBe(24);

    $kept = TerytLocality::query()->where('simc', '0900002')->firstOrFail();
    expect($kept->slug)->toBe('moj-slug')
        ->and((float) $kept->lat)->toBe(52.1234567)
        ->and($kept->coordinates_source)->toBe('manual');
    expect(TerytLocality::query()->where('simc', '0900003')->value('name'))->toBe('Dopiewiec');
});

it('w trybie próbnym niczego nie zapisuje, ale liczy zmiany', function () {
    $result = app(ImportTerytData::class)->handle(tercFixture(), simcFixture(), dryRun: true);

    expect($result->dryRun)->toBeTrue()
        ->and($result->municipalitiesAdded)->toBe(7)
        ->and($result->localitiesAdded)->toBe(24)
        ->and(TerytMunicipality::query()->count())->toBe(0)
        ->and(TerytLocality::query()->count())->toBe(0)
        ->and(app(SettingsRepository::class)->get('teryt.imported_at'))->toBeNull();
});

it('wyłącza miejscowości, których nie ma w pliku (gdy plik jest kompletny)', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());
    $municipalityId = TerytMunicipality::query()->value('id');
    TerytLocality::factory()->count(2)->create(['municipality_id' => $municipalityId]);

    $result = app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    expect($result->deactivated)->toBe(2)
        ->and($result->deactivationSkipped)->toBeFalse()
        ->and(TerytLocality::query()->where('is_active', false)->count())->toBe(2)
        ->and(TerytLocality::query()->where('is_active', true)->count())->toBe(24);
});

it('nie wyłącza niczego, gdy plik ma mniej niż 90% dotychczasowych miejscowości (obcięty plik)', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());
    TerytLocality::factory()->count(5)->create(['municipality_id' => TerytMunicipality::query()->value('id')]);

    $result = app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    expect($result->deactivationSkipped)->toBeTrue()
        ->and($result->deactivated)->toBe(0)
        ->and(TerytLocality::query()->where('is_active', false)->count())->toBe(0);
});

it('ponownie aktywuje miejscowość, która wróciła do pliku', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());
    TerytLocality::query()->where('simc', '0900002')->update(['is_active' => false]);

    app(ImportTerytData::class)->handle(tercFixture(), simcFixture());

    expect(TerytLocality::query()->where('simc', '0900002')->value('is_active'))->toBeTrue();
});

it('przerywa po limicie czasu i pozwala wznowić od wskazanego kodu', function () {
    $first = app(ImportTerytData::class)->handle(tercFixture(), simcFixture(), chunkSize: 5, maxSeconds: 0);

    expect($first->complete)->toBeFalse()
        ->and($first->resumeFrom)->toBe('0900006')
        ->and($first->deactivationSkipped)->toBeTrue()
        ->and(TerytLocality::query()->count())->toBe(5)
        // Częściowy import nie zapisuje dat stanu.
        ->and(app(SettingsRepository::class)->get('teryt.simc_state_date'))->toBeNull();

    $second = app(ImportTerytData::class)->handle(tercFixture(), simcFixture(), chunkSize: 5, resumeFrom: $first->resumeFrom);

    expect($second->complete)->toBeTrue()
        ->and(TerytLocality::query()->count())->toBe(24)
        ->and($second->deactivationSkipped)->toBeTrue();
});

it('zgłasza błąd, gdy kod wznowienia nie istnieje w pliku', function () {
    app(ImportTerytData::class)->handle(tercFixture(), simcFixture(), resumeFrom: '9999999');
})->throws(TerytImportException::class, 'Kod wznowienia 9999999');

it('zgłasza czytelny błąd przy braku wymaganej kolumny', function () {
    $broken = tempCsv("WOJ;POW;GMI;NAZWA;STAN_NA\r\n02;;;X;2026-01-01\r\n");

    app(ImportTerytData::class)->handle($broken, simcFixture());
})->throws(TerytImportException::class, 'brakuje kolumn: RODZ');

it('odrzuca plik w złym kodowaniu (np. Windows-1250)', function () {
    $header = "WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA\r\n";
    $cp1250 = $header."12;;;;MA\xA3OPOLSKIE;wojew\xF3dztwo;2026-01-01\r\n";

    app(ImportTerytData::class)->handle(tempCsv($cp1250), simcFixture());
})->throws(TerytImportException::class, 'nie jest w UTF-8');

it('odrzuca wiersz z za małą liczbą pól', function () {
    $short = tempCsv("WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA\r\n12;;;;MALOPOLSKIE\r\n");

    app(ImportTerytData::class)->handle($short, simcFixture());
})->throws(TerytImportException::class, 'ma 5 pól zamiast 7');

it('odrzuca nieistniejący plik', function () {
    app(ImportTerytData::class)->handle('/nie/ma/takiego/pliku.csv', simcFixture());
})->throws(TerytImportException::class, 'Nie można otworzyć pliku');

it('pomija miejscowości z gminą nieobecną w pliku TERC i liczy je', function () {
    $oneMunicipality = tempCsv(
        "WOJ;POW;GMI;RODZ;NAZWA;NAZWA_DOD;STAN_NA\r\n"
        ."30;;;;WIELKOPOLSKIE;województwo;2026-01-01\r\n"
        ."30;64;;;poznański;powiat;2026-01-01\r\n"
        ."30;64;03;2;Dopiewo;gmina wiejska;2026-01-01\r\n"
    );

    $result = app(ImportTerytData::class)->handle($oneMunicipality, simcFixture());

    expect($result->localitiesAdded)->toBe(9)
        ->and($result->skippedWithoutMunicipality)->toBe(15);
});

it('komenda teryt:import wypisuje podsumowanie i działa na ścieżkach bezwzględnych', function () {
    $this->artisan('teryt:import', ['--terc' => tercFixture(), '--simc' => simcFixture(), '--chunk' => 10])
        ->expectsOutputToContain('Import zakończony.')
        ->expectsOutputToContain('Miejscowości: dodano 24')
        ->assertSuccessful();

    expect(TerytLocality::query()->count())->toBe(24);
});

it('komenda teryt:import --dry-run nic nie zapisuje', function () {
    $this->artisan('teryt:import', ['--terc' => tercFixture(), '--simc' => simcFixture(), '--dry-run' => true])
        ->expectsOutputToContain('TRYB PRÓBNY')
        ->assertSuccessful();

    expect(TerytLocality::query()->count())->toBe(0);
});

it('komenda teryt:import odmawia, gdy brakuje plików', function () {
    $this->artisan('teryt:import', ['--terc' => 'nie-ma.csv', '--simc' => simcFixture()])
        ->expectsOutputToContain('Podaj istniejące pliki')
        ->assertExitCode(2);
});

it('komenda teryt:import wypisuje błąd pliku bez śladu stosu', function () {
    $broken = tempCsv("A;B\r\n1;2\r\n");

    $this->artisan('teryt:import', ['--terc' => $broken, '--simc' => simcFixture()])
        ->expectsOutputToContain('brakuje kolumn')
        ->assertFailed();
});
