<?php

/*
| Przypadki brzegowe geokodowania (tester, zadanie 003). Wszystkie żądania HTTP są fałszowane (Http::fake),
| żaden test nie łączy się z prawdziwym Nominatim. Uzupełnia GeocodingServiceTest.
*/

use App\Domain\Geo\Actions\ResolveLocalityCoordinates;
use App\Domain\Geo\Contracts\GeocodingProvider;
use App\Domain\Geo\Data\AddressQuery;
use App\Domain\Geo\Data\ProviderResult;
use App\Domain\Geo\Enums\GeocodeStatus;
use App\Domain\Geo\Models\GeocodeCacheEntry;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Geo\Services\GeocodingService;
use App\Domain\Geo\Support\Sleeper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Sleeper z kontrolowanym czasem; opcjonalny hook wywoływany w trakcie "spania" (symulacja innego procesu). */
class EdgeSleeper extends Sleeper
{
    public float $time = 5000.0;

    /** @var list<int> */
    public array $sleeps = [];

    public ?Closure $onSleep = null;

    public function now(): float
    {
        return $this->time;
    }

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->sleeps[] = $milliseconds;
        $this->time += $milliseconds / 1000;

        if ($this->onSleep !== null) {
            ($this->onSleep)();
        }
    }
}

beforeEach(function () {
    config([
        'geocoding.contact' => 'kontakt@example.pl',
        'geocoding.base_url' => 'https://nominatim.openstreetmap.org',
    ]);
    $this->sleeper = new EdgeSleeper;
    $this->app->instance(Sleeper::class, $this->sleeper);
    Cache::flush();
});

/** @return array<int, array<string, string>> */
function edgeHit(float $lat = 52.31, float $lng = 16.62, string $type = 'house'): array
{
    return [['lat' => (string) $lat, 'lon' => (string) $lng, 'addresstype' => $type]];
}

function edgeAddress(): AddressQuery
{
    return new AddressQuery(street: 'Polna', houseNumber: '12', postalCode: '64-320', city: 'Dąbrówka', simc: '0900001');
}

it('błąd sieci nie jest zapamiętywany jako "nie znaleziono": po przerwie następne wywołanie pyta ponownie i znajduje adres', function () {
    Http::fake(['*' => Http::sequence()->pushFailedConnection('timeout')->push(edgeHit(), 200)]);
    $service = app(GeocodingService::class);

    $first = $service->geocodeAddress(edgeAddress());
    expect($first->status)->toBe(GeocodeStatus::Unavailable)->and(GeocodeCacheEntry::query()->count())->toBe(0);

    // Wyłącznik jest aktywny: drugie wywołanie nie pyta usługi.
    expect($service->geocodeAddress(edgeAddress())->reason)->toBe('cooldown');
    Http::assertSentCount(1);

    // Po upływie przerwy serwis znowu pyta i tym razem zapisuje trafienie.
    Cache::forget('geocoding:down');
    $third = $service->geocodeAddress(edgeAddress());

    expect($third->status)->toBe(GeocodeStatus::Found)->and(GeocodeCacheEntry::query()->count())->toBe(1);
    Http::assertSentCount(2);
});

it('"nie znaleziono" (pusta lista z usługi) jest zapamiętane, ale to samo zapytanie po 8 dniach pyta ponownie', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $service = app(GeocodingService::class);

    $service->geocodeAddress(new AddressQuery(city: 'Nieistniejąca'));
    Http::assertSentCount(1);

    $this->travel(6)->days();
    expect($service->geocodeAddress(new AddressQuery(city: 'Nieistniejąca'))->fromCache)->toBeTrue();
    Http::assertSentCount(1);

    $this->travel(2)->days();
    $service->geocodeAddress(new AddressQuery(city: 'Nieistniejąca'));
    Http::assertSentCount(2);
    expect(GeocodeCacheEntry::query()->count())->toBe(1);
});

it('wynik z cache jest zwracany także podczas działania wyłącznika awarii', function () {
    Http::fake(['*' => Http::response(edgeHit(), 200)]);
    $service = app(GeocodingService::class);
    $service->geocodeAddress(edgeAddress());

    Cache::put('geocoding:down', 'http_500', 300);
    $result = $service->geocodeAddress(edgeAddress());

    expect($result->isFound())->toBeTrue()->and($result->fromCache)->toBeTrue();
    Http::assertSentCount(1);
});

it('błąd 404 i 400 daje Unavailable z krótkim wyłącznikiem i bez wpisu w cache', function (int $status) {
    Http::fake(['*' => Http::response('x', $status)]);

    $result = app(GeocodingService::class)->geocodeAddress(edgeAddress());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)
        ->and($result->reason)->toBe('http_'.$status)
        ->and(Cache::has('geocoding:down'))->toBeTrue()
        ->and(GeocodeCacheEntry::query()->count())->toBe(0);
})->with([400, 404, 418, 502, 503]);

it('nieprawidłowe odpowiedzi 200 dają Unavailable i nigdy nie trafiają do cache', function (mixed $body) {
    Http::fake(['*' => Http::response($body, 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(edgeAddress());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)->and(GeocodeCacheEntry::query()->count())->toBe(0);
})->with([
    'obiekt zamiast listy' => [['error' => 'Rate limited']],
    'brak lat/lon' => [[['display_name' => 'x']]],
    'lat nie jest liczbą' => [[['lat' => 'abc', 'lon' => '16.6']]],
    'lat to tablica' => [[['lat' => [1], 'lon' => '16.6']]],
    'element nie jest tablicą' => [['tekst']],
    'JSON null' => ['null'],
    'kod HTML' => ['<html><body>Bad gateway</body></html>'],
]);

it('współrzędne na granicach Polski są akceptowane, a poza nimi oraz 0,0 odrzucane', function (float $lat, float $lng, GeocodeStatus $expected) {
    Http::fake(['*' => Http::response(edgeHit($lat, $lng), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(new AddressQuery(city: 'Test'));

    expect($result->status)->toBe($expected);
})->with([
    'północna granica' => [54.9, 18.0, GeocodeStatus::Found],
    'tuż za północną' => [54.91, 18.0, GeocodeStatus::NotFound],
    'południowa granica' => [48.9, 20.0, GeocodeStatus::Found],
    'tuż za południową' => [48.89, 20.0, GeocodeStatus::NotFound],
    'zachodnia granica' => [52.0, 14.0, GeocodeStatus::Found],
    'tuż za zachodnią' => [52.0, 13.99, GeocodeStatus::NotFound],
    'wschodnia granica' => [52.0, 24.2, GeocodeStatus::Found],
    'tuż za wschodnią' => [52.0, 24.21, GeocodeStatus::NotFound],
    'zero zero' => [0.0, 0.0, GeocodeStatus::NotFound],
]);

it('wyjątek dostawcy nie wychodzi na zewnątrz, zwalnia blokadę i włącza wyłącznik; w logu nie ma adresu', function () {
    $this->app->instance(GeocodingProvider::class, new class implements GeocodingProvider
    {
        public function isConfigured(): bool
        {
            return true;
        }

        public function search(AddressQuery $query): ProviderResult
        {
            throw new RuntimeException('Polna 12 Dąbrówka 64-320 awaria');
        }
    });
    Log::spy();

    $result = app(GeocodingService::class)->geocodeAddress(edgeAddress());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)
        ->and($result->reason)->toBe('error')
        ->and(Cache::has('geocoding:down'))->toBeTrue();

    // Blokada nie została zawieszona: inny proces może ją od razu zdobyć.
    $lock = Cache::lock('nominatim', 10);
    expect($lock->get())->toBeTrue();
    $lock->release();

    Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) {
        $dump = json_encode($context, JSON_UNESCAPED_UNICODE);

        return ! str_contains($dump, 'Polna') && ! str_contains($dump, 'Dąbrówka') && ! str_contains($dump, '64-320');
    });
});

it('blokada limitu jest zwolniona po udanym zapytaniu', function () {
    Http::fake(['*' => Http::response(edgeHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(edgeAddress());

    $lock = Cache::lock('nominatim', 10);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

it('zajęta blokada: nie ma zapytania, nie ma zapisu w cache i nie ma zapytania awaryjnego o samą miejscowość', function () {
    config(['geocoding.lock_wait_seconds' => 0]);
    Http::fake(['*' => Http::response(edgeHit(), 200)]);
    $lock = Cache::lock('nominatim', 10);
    $lock->get();

    $result = app(GeocodingService::class)->geocodeAddress(edgeAddress());
    $lock->release();

    expect($result->reason)->toBe('busy')
        ->and(GeocodeCacheEntry::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('po zwolnieniu blokady kolejne wywołanie działa normalnie', function () {
    config(['geocoding.lock_wait_seconds' => 0]);
    Http::fake(['*' => Http::response(edgeHit(), 200)]);
    $lock = Cache::lock('nominatim', 10);
    $lock->get();

    expect(app(GeocodingService::class)->geocodeAddress(edgeAddress())->reason)->toBe('busy');

    $lock->release();

    expect(app(GeocodingService::class)->geocodeAddress(edgeAddress())->status)->toBe(GeocodeStatus::Found);
});

it('nieudane zapytanie też liczy się do limitu 1/s (następne czeka)', function () {
    Http::fake(['*' => Http::sequence()->pushFailedConnection('timeout')->push(edgeHit(), 200)]);
    $service = app(GeocodingService::class);

    $service->geocodeAddress(new AddressQuery(city: 'Pierwsza'));
    Cache::forget('geocoding:down');
    $this->sleeper->time += 0.1;
    $service->geocodeAddress(new AddressQuery(city: 'Druga'));

    expect($this->sleeper->sleeps)->toBe([1000]);
});

it('gdy zapytanie o adres nie znajduje wyniku, a zapytanie awaryjne kończy się awarią, nic nie trafia do cache', function () {
    Http::fake(['*' => Http::sequence()->push([], 200)->pushStatus(500)]);

    $result = app(GeocodingService::class)->geocodeAddress(edgeAddress());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)
        ->and(GeocodeCacheEntry::query()->count())->toBe(0)
        ->and(Cache::has('geocoding:down'))->toBeTrue();
});

it('adres bez ulicy, który nie ma wyniku, wykonuje tylko jedno zapytanie (bez zapytania awaryjnego)', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(new AddressQuery(postalCode: '64-320', city: 'Dąbrówka'));

    expect($result->status)->toBe(GeocodeStatus::NotFound);
    Http::assertSentCount(1);
});

it('znaki specjalne, HTML, emoji i bardzo długi tekst w adresie nie przerywają działania i są kodowane w adresie URL', function () {
    Http::fake(['*' => Http::response(edgeHit(), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(new AddressQuery(
        street: '<script>alert(1)</script> "; DROP TABLE x; -- '.str_repeat('Ł', 400),
        houseNumber: "12\u{1F600}",
        postalCode: "64-320'; --",
        city: 'Dąbrówka & Co. <b>',
    ));

    expect($result->status)->toBe(GeocodeStatus::Found);
    Http::assertSent(function (Request $request) {
        return ! str_contains($request->url(), '<script>')
            && ! str_contains($request->url(), ' ')
            && str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search?');
    });
    // W cache jest tylko skrót (64 znaki), nigdy tekst.
    expect(strlen((string) GeocodeCacheEntry::query()->value('query_hash')))->toBe(64);
});

it('puste i złożone z samych spacji pola adresu są traktowane jak brak zapytania', function () {
    Http::fake();

    $result = app(GeocodingService::class)->geocodeAddress(new AddressQuery(street: '   ', city: '', postalCode: null));

    expect($result->status)->toBe(GeocodeStatus::NotFound);
    Http::assertNothingSent();
});

it('wieś o tej samej nazwie w różnych powiatach daje osobne wpisy w cache i osobne zapytania', function () {
    Http::fake(['*' => Http::sequence()->push(edgeHit(52.0, 16.0, 'village'), 200)->push(edgeHit(50.0, 19.9, 'village'), 200)]);
    $a = TerytLocality::factory()->create([
        'name' => 'Dąbrówka', 'simc' => '9000001', 'slug' => 'dabrowka-a',
        'municipality_id' => TerytMunicipality::factory()->create(['county_name' => 'poznański', 'voivodeship_name' => 'wielkopolskie']),
    ]);
    $b = TerytLocality::factory()->create([
        'name' => 'Dąbrówka', 'simc' => '9000002', 'slug' => 'dabrowka-b',
        'municipality_id' => TerytMunicipality::factory()->create(['county_name' => 'krakowski', 'voivodeship_name' => 'małopolskie']),
    ]);

    $resolve = app(ResolveLocalityCoordinates::class);
    $ca = $resolve->handle($a);
    $cb = $resolve->handle($b);

    expect($ca->lat)->toBe(52.0)->and($cb->lat)->toBe(50.0)
        ->and(GeocodeCacheEntry::query()->count())->toBe(2);
    Http::assertSentCount(2);
});

it('"nie znaleziono" dla miejscowości nie zapisuje współrzędnych, a kolejne wywołanie korzysta z cache', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $locality = TerytLocality::factory()->create();

    expect(app(ResolveLocalityCoordinates::class)->handle($locality))->toBeNull();
    expect(app(ResolveLocalityCoordinates::class)->handle($locality->fresh()))->toBeNull();

    $fresh = $locality->fresh();
    expect($fresh->lat)->toBeNull()->and($fresh->geocoded_at)->toBeNull();
    // Miejscowość bez ulicy: jedno zapytanie, drugie wywołanie z cache.
    Http::assertSentCount(1);
});

it('miejscowość ze znanymi współrzędnymi nie powoduje żadnego ruchu sieciowego', function () {
    Http::fake();
    $locality = TerytLocality::factory()->withCoordinates(51.1, 17.0)->create();

    $coordinates = app(GeocodingService::class)->geocodeLocality($locality);

    expect($coordinates->lat)->toBe(51.1);
    Http::assertNothingSent();
});

it('brak adresu kontaktowego: wiele wywołań to zero zapytań i dokładnie jedna linia ostrzeżenia na dobę', function () {
    config(['geocoding.contact' => null]);
    Http::fake();
    Log::spy();
    $service = app(GeocodingService::class);

    foreach (range(1, 5) as $i) {
        $service->geocodeAddress(new AddressQuery(city: "Miasto {$i}"));
    }

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});

it('adres kontaktowy ze znakami nowej linii nie trafia do nagłówka User-Agent (wstrzyknięcie nagłówka)', function () {
    config(['geocoding.contact' => "kontakt@example.pl\r\nX-Evil: 1"]);
    Http::fake(['*' => Http::response(edgeHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(edgeAddress());

    // Niepoprawny adres e-mail jest traktowany jak brak kontaktu, więc żądanie nie jest wysyłane.
    Http::assertNothingSent();
});

it('geocode:prune działa na pustej tabeli i usuwa wiele wygasłych wpisów naraz', function () {
    $this->artisan('geocode:prune')->expectsOutputToContain('Usunięto wygasłych wpisów geokodowania: 0')->assertSuccessful();

    foreach (range(1, 50) as $i) {
        GeocodeCacheEntry::query()->create([
            'query_hash' => hash('sha256', (string) $i), 'status' => 'not_found', 'provider' => 'nominatim',
            'expires_at' => $i <= 30 ? now()->subHour() : now()->addHour(),
        ]);
    }

    $this->artisan('geocode:prune')->expectsOutputToContain('Usunięto wygasłych wpisów geokodowania: 30')->assertSuccessful();
    expect(GeocodeCacheEntry::query()->count())->toBe(20);
});

/*
| Usterki DROBNY-1 i DROBNY-2 z sekcji "Testy" zadania 003 (poprawione, testy aktywne).
*/

it('nazwa hosta publicznego Nominatim z wielkimi literami też wymaga adresu kontaktowego', function () {
    config(['geocoding.contact' => null, 'geocoding.base_url' => 'https://NOMINATIM.OpenStreetMap.org']);
    Http::fake(['*' => Http::response(edgeHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(edgeAddress());

    Http::assertNothingSent();
});

it('po zdobyciu blokady sprawdza cache ponownie i nie pyta usługi, gdy inny proces zdążył zapisać wynik', function () {
    Http::fake(['*' => Http::response(edgeHit(), 200)]);
    $service = app(GeocodingService::class);
    $service->geocodeAddress(new AddressQuery(city: 'Pierwsza'));

    $second = new AddressQuery(city: 'Druga');
    // W trakcie oczekiwania na odstęp 1,1 s "inny proces" zapisuje wynik dla tego samego adresu.
    $this->sleeper->onSleep = function () use ($second): void {
        GeocodeCacheEntry::query()->create([
            'query_hash' => $second->hash(), 'status' => 'found', 'lat' => 52.1, 'lng' => 16.1,
            'precision' => 'locality', 'provider' => 'nominatim', 'expires_at' => now()->addDays(180),
        ]);
    };
    $this->sleeper->time += 0.1;

    $service->geocodeAddress($second);

    Http::assertSentCount(1);
});
