<?php

/*
| Testy geokodowania: wszystkie żądania HTTP są fałszowane (Http::fake), żaden test nie łączy się z Nominatim.
*/

use App\Domain\Geo\Actions\ResolveLocalityCoordinates;
use App\Domain\Geo\Data\AddressQuery;
use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Enums\GeocodeStatus;
use App\Domain\Geo\Models\GeocodeCacheEntry;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Geo\Services\GeocodingService;
use App\Domain\Geo\Support\Sleeper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Sleeper z kontrolowanym czasem: `sleepMilliseconds` przesuwa zegar zamiast usypiać proces. */
class FakeSleeper extends Sleeper
{
    public float $time = 1000.0;

    /** @var list<int> */
    public array $sleeps = [];

    public function now(): float
    {
        return $this->time;
    }

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->sleeps[] = $milliseconds;
        $this->time += $milliseconds / 1000;
    }
}

beforeEach(function () {
    config([
        'geocoding.contact' => 'kontakt@example.pl',
        'geocoding.base_url' => 'https://nominatim.openstreetmap.org',
    ]);
    $this->sleeper = new FakeSleeper;
    $this->app->instance(Sleeper::class, $this->sleeper);
    Cache::flush();
});

/** Odpowiedź Nominatim jsonv2 z jednym trafieniem. */
function nominatimHit(float $lat = 52.31, float $lng = 16.62, string $addressType = 'house'): array
{
    return [['lat' => (string) $lat, 'lon' => (string) $lng, 'addresstype' => $addressType, 'display_name' => 'NIE-ZAPISUJ-MNIE']];
}

function address(): AddressQuery
{
    return new AddressQuery(street: 'Polna', houseNumber: '12', postalCode: '64-320', city: 'Dąbrówka', simc: '0900001');
}

it('znajduje adres, wysyła poprawne parametry i zapisuje wynik w cache', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->status)->toBe(GeocodeStatus::Found)
        ->and($result->lat)->toBe(52.31)
        ->and($result->lng)->toBe(16.62)
        ->and($result->precision)->toBe(GeocodePrecision::Address)
        ->and($result->fromCache)->toBeFalse();

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search?')
            && $query['format'] === 'jsonv2'
            && $query['limit'] === '1'
            && $query['countrycodes'] === 'pl'
            && $query['street'] === '12 Polna'
            && $query['city'] === 'Dąbrówka'
            && $query['postalcode'] === '64-320'
            && ! array_key_exists('q', $query);
    });

    expect(GeocodeCacheEntry::query()->count())->toBe(1);
});

it('wysyła własny User-Agent z adresem kontaktowym', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(address());

    Http::assertSent(function (Request $request) {
        $userAgent = $request->header('User-Agent')[0] ?? '';

        return str_starts_with($userAgent, 'kontroladomu.pl/0.1 (+https://kontroladomu.pl; kontakt@example.pl)');
    });
});

it('wysyła tylko pola adresowe (bez danych osobowych)', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(address());

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        $allowed = ['format', 'limit', 'countrycodes', 'accept-language', 'addressdetails', 'street', 'city', 'postalcode', 'county', 'state'];

        return array_diff(array_keys($query), $allowed) === [] && $request->method() === 'GET' && $request->body() === '';
    });
});

it('drugie takie samo zapytanie bierze z cache bez ruchu sieciowego', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);
    $service = app(GeocodingService::class);

    $service->geocodeAddress(address());
    $second = $service->geocodeAddress(new AddressQuery(street: 'POLNA', houseNumber: '12', postalCode: '64320', city: 'dabrowka', simc: '0900001'));

    expect($second->fromCache)->toBeTrue()->and($second->isFound())->toBeTrue();
    Http::assertSentCount(1);
});

it('w cache nie zapisuje tekstu adresu ani nazwy z Nominatim, tylko skrót i liczby', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(address());

    $row = (array) DB::table('geocode_cache')->first();
    $dump = mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE));

    expect($row['query_hash'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($dump)->not->toContain('polna')
        ->not->toContain('dąbrówka')
        ->not->toContain('64-320')
        ->not->toContain('nie-zapisuj-mnie')
        ->and($row['status'])->toBe('found')
        ->and($row['provider'])->toBe('nominatim');
});

it('po braku adresu z ulicą próbuje samej miejscowości i zwraca dokładność locality', function () {
    Http::fake(['*' => Http::sequence()->push([], 200)->push(nominatimHit(52.0, 16.0, 'village'), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->status)->toBe(GeocodeStatus::Found)
        ->and($result->precision)->toBe(GeocodePrecision::Locality);
    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ! array_key_exists('street', $query) && $query['city'] === 'Dąbrówka';
    });
    // Drugie zapytanie też przeszło przez limit 1/s.
    expect($this->sleeper->sleeps)->toHaveCount(1);
});

it('zapamiętuje "nie znaleziono" na 7 dni i nie pyta ponownie', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $service = app(GeocodingService::class);

    $first = $service->geocodeAddress(address());
    $second = $service->geocodeAddress(address());

    expect($first->status)->toBe(GeocodeStatus::NotFound)
        ->and($second->status)->toBe(GeocodeStatus::NotFound)
        ->and($second->fromCache)->toBeTrue();
    // Pierwsze wywołanie: adres + miejscowość = 2 zapytania; drugie: z cache.
    Http::assertSentCount(2);

    $entry = GeocodeCacheEntry::query()->firstOrFail();
    expect($entry->status)->toBe(GeocodeStatus::NotFound)
        ->and($entry->lat)->toBeNull()
        ->and((int) round(now()->diffInDays($entry->expires_at)))->toBe(7);
});

it('trafienie jest ważne w cache 180 dni', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    app(GeocodingService::class)->geocodeAddress(address());

    expect((int) round(now()->diffInDays(GeocodeCacheEntry::query()->firstOrFail()->expires_at)))->toBe(180);
});

it('ignoruje wygasły wpis i nadpisuje go jednym wierszem', function () {
    Http::fake(['*' => Http::response(nominatimHit(52.5, 16.5), 200)]);
    GeocodeCacheEntry::query()->create([
        'query_hash' => address()->hash(), 'status' => 'found', 'lat' => 50.0, 'lng' => 20.0,
        'precision' => 'address', 'provider' => 'nominatim', 'expires_at' => now()->subDay(),
    ]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->lat)->toBe(52.5)->and($result->fromCache)->toBeFalse()
        ->and(GeocodeCacheEntry::query()->count())->toBe(1)
        ->and((float) GeocodeCacheEntry::query()->firstOrFail()->lat)->toBe(52.5);
});

it('traktuje współrzędne spoza Polski jak brak wyniku', function () {
    Http::fake(['*' => Http::response(nominatimHit(48.85, 2.35), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(new AddressQuery(city: 'Paryż'));

    expect($result->status)->toBe(GeocodeStatus::NotFound);
});

it('odpowiedź 429 daje Unavailable, włącza wyłącznik na 30 minut i nic nie zapisuje w cache', function () {
    Http::fake(['*' => Http::response('Too Many Requests', 429)]);
    Log::spy();
    $service = app(GeocodingService::class);

    $first = $service->geocodeAddress(address());
    $second = $service->geocodeAddress(new AddressQuery(city: 'Inna'));

    expect($first->status)->toBe(GeocodeStatus::Unavailable)
        ->and($first->reason)->toBe('rate_limited_or_blocked')
        ->and($second->status)->toBe(GeocodeStatus::Unavailable)
        ->and($second->reason)->toBe('cooldown')
        ->and(Cache::has('geocoding:down'))->toBeTrue()
        ->and(GeocodeCacheEntry::query()->count())->toBe(0);
    // Wyłącznik: drugie wywołanie nie wysłało zapytania.
    Http::assertSentCount(1);

    // Dokładnie jedna linia ostrzeżenia, bez adresu.
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => ! str_contains(json_encode($context), 'Polna') && isset($context['query_hash'])
    );

    $this->travel(29)->minutes();
    expect(Cache::has('geocoding:down'))->toBeTrue();
    $this->travel(2)->minutes();
    expect(Cache::has('geocoding:down'))->toBeFalse();
});

it('odpowiedź 403 też włącza wyłącznik', function () {
    Http::fake(['*' => Http::response('Forbidden', 403)]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)->and(Cache::has('geocoding:down'))->toBeTrue();
});

it('błąd serwera 500 daje Unavailable, krótki wyłącznik i brak wpisu w cache', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)
        ->and($result->reason)->toBe('http_500')
        ->and(GeocodeCacheEntry::query()->count())->toBe(0);

    $this->travel(6)->minutes();
    expect(Cache::has('geocoding:down'))->toBeFalse();
});

it('timeout lub brak połączenia daje Unavailable i nie rzuca wyjątku', function () {
    Http::fake(['*' => Http::failedConnection('timeout')]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)
        ->and($result->reason)->toBe('network')
        ->and(GeocodeCacheEntry::query()->count())->toBe(0);
});

it('niepoprawna odpowiedź (nie tablica) daje Unavailable', function () {
    Http::fake(['*' => Http::response('<html>to nie JSON</html>', 200)]);

    expect(app(GeocodingService::class)->geocodeAddress(address())->status)->toBe(GeocodeStatus::Unavailable);
});

it('bez adresu kontaktowego nie wysyła żadnych zapytań do publicznego Nominatim', function () {
    config(['geocoding.contact' => null]);
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->status)->toBe(GeocodeStatus::Unavailable)->and($result->reason)->toBe('not_configured');
    Http::assertNothingSent();
});

it('bez adresu kontaktowego nadal korzysta z cache', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);
    app(GeocodingService::class)->geocodeAddress(address());

    config(['geocoding.contact' => '']);
    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->isFound())->toBeTrue()->and($result->fromCache)->toBeTrue();
    Http::assertSentCount(1);
});

it('odrzuca niepoprawny adres kontaktowy jak brak', function () {
    config(['geocoding.contact' => 'to-nie-jest-email']);
    Http::fake();

    app(GeocodingService::class)->geocodeAddress(address());

    Http::assertNothingSent();
});

it('własny serwer Nominatim działa bez adresu kontaktowego', function () {
    config(['geocoding.contact' => null, 'geocoding.base_url' => 'https://nominatim.example.pl/']);
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);

    $result = app(GeocodingService::class)->geocodeAddress(address());

    expect($result->isFound())->toBeTrue();
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://nominatim.example.pl/search?'));
});

it('nie pyta o puste zapytanie', function () {
    Http::fake();

    $result = app(GeocodingService::class)->geocodeAddress(new AddressQuery);

    expect($result->status)->toBe(GeocodeStatus::NotFound);
    Http::assertNothingSent();
});

it('zachowuje odstęp 1,1 s między zapytaniami z różnych wywołań', function () {
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);
    $service = app(GeocodingService::class);

    $service->geocodeAddress(new AddressQuery(city: 'Pierwsza'));
    expect($this->sleeper->sleeps)->toBe([]);

    // Tylko 0,2 s później: musi poczekać brakujące 0,9 s.
    $this->sleeper->time += 0.2;
    $service->geocodeAddress(new AddressQuery(city: 'Druga'));
    expect($this->sleeper->sleeps)->toBe([900]);

    // Po 5 s nie czeka wcale.
    $this->sleeper->time += 5;
    $service->geocodeAddress(new AddressQuery(city: 'Trzecia'));
    expect($this->sleeper->sleeps)->toBe([900]);
});

it('nie wysyła zapytania, gdy blokada limitu jest zajęta', function () {
    config(['geocoding.lock_wait_seconds' => 0]);
    Http::fake(['*' => Http::response(nominatimHit(), 200)]);
    $lock = Cache::lock('nominatim', 10);
    $lock->get();

    $result = app(GeocodingService::class)->geocodeAddress(address());
    $lock->release();

    expect($result->status)->toBe(GeocodeStatus::Unavailable)->and($result->reason)->toBe('busy');
    Http::assertNothingSent();
    // Zajęta blokada to nie awaria usługi: wyłącznik nie jest włączany.
    expect(Cache::has('geocoding:down'))->toBeFalse();
});

it('wyznacza współrzędne miejscowości z TERYT i zapisuje je w bazie', function () {
    Http::fake(['*' => Http::response(nominatimHit(52.3, 16.6, 'village'), 200)]);
    $municipality = TerytMunicipality::factory()->create(['county_name' => 'poznański', 'voivodeship_name' => 'wielkopolskie']);
    $locality = TerytLocality::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Dąbrówka']);

    $coordinates = app(ResolveLocalityCoordinates::class)->handle($locality);

    expect($coordinates->lat)->toBe(52.3);
    $fresh = $locality->fresh();
    expect((float) $fresh->lat)->toBe(52.3)
        ->and((float) $fresh->lng)->toBe(16.6)
        ->and($fresh->coordinates_source)->toBe('nominatim')
        ->and($fresh->geocoded_at)->not->toBeNull();

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['city'] === 'Dąbrówka' && $query['county'] === 'powiat poznański'
            && $query['state'] === 'województwo wielkopolskie' && ! array_key_exists('street', $query);
    });

    // Kolejne wywołanie nie robi żadnego zapytania.
    app(ResolveLocalityCoordinates::class)->handle($fresh);
    Http::assertSentCount(1);
});

it('miasto na prawach powiatu przekazuje jako powiat bez przedrostka', function () {
    Http::fake(['*' => Http::response(nominatimHit(52.4, 16.9, 'city'), 200)]);
    $municipality = TerytMunicipality::factory()->create(['county_name' => 'Poznań', 'voivodeship_name' => 'wielkopolskie']);
    $locality = TerytLocality::factory()->city()->create(['municipality_id' => $municipality->id, 'name' => 'Poznań']);

    app(ResolveLocalityCoordinates::class)->handle($locality);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['county'] === 'Poznań';
    });
});

it('nie zmienia miejscowości, gdy geokodowanie jest niedostępne', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $locality = TerytLocality::factory()->create();

    $coordinates = app(ResolveLocalityCoordinates::class)->handle($locality);

    expect($coordinates)->toBeNull()->and($locality->fresh()->lat)->toBeNull();
});

it('komenda geocode:prune usuwa tylko wygasłe wpisy', function () {
    GeocodeCacheEntry::query()->create(['query_hash' => str_repeat('a', 64), 'status' => 'not_found', 'provider' => 'nominatim', 'expires_at' => now()->subDay()]);
    GeocodeCacheEntry::query()->create(['query_hash' => str_repeat('b', 64), 'status' => 'not_found', 'provider' => 'nominatim', 'expires_at' => now()->addDay()]);

    $this->artisan('geocode:prune')->expectsOutputToContain('Usunięto wygasłych wpisów geokodowania: 1')->assertSuccessful();

    expect(GeocodeCacheEntry::query()->pluck('query_hash')->all())->toBe([str_repeat('b', 64)]);
});

it('skrót adresu nie zależy od wielkości liter, polskich znaków ani formatu kodu pocztowego', function () {
    $a = new AddressQuery('Łąkowa', '5', '64-320', 'Dąbrówka', '0900001');
    $b = new AddressQuery('lakowa', '5', '64320', 'DABROWKA', '0900001');
    $other = new AddressQuery('Łąkowa', '5', '64-320', 'Dąbrówka', '0900007');

    expect($a->hash())->toBe($b->hash())->not->toBe($other->hash());
});
