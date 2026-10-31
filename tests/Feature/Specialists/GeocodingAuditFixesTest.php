<?php

/*
| Podzadanie 004.1: poprawki geokodowania z audytu zadania 003 (HMAC w cache, brak przekierowań, limit odpowiedzi,
| https dla NOMINATIM_URL, krótszy TTL dla adresów z ulicą). Żadne żądanie nie wychodzi do prawdziwego Nominatim.
*/

use App\Domain\Geo\Data\AddressQuery;
use App\Domain\Geo\Enums\GeocodeStatus;
use App\Domain\Geo\Models\GeocodeCacheEntry;
use App\Domain\Geo\Services\GeocodingService;
use App\Domain\Geo\Services\NominatimProvider;
use App\Support\Security\ProductionConfigGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'geocoding.contact' => 'kontakt@example.pl',
        'geocoding.base_url' => 'https://nominatim.openstreetmap.org',
    ]);
    Cache::flush();
});

it('skrót adresu to HMAC zależny od APP_KEY, a nie zwykły SHA-256', function () {
    $query = new AddressQuery(city: 'Dąbrówka', simc: '0900001');

    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $first = $query->hash();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
    $second = $query->hash();

    expect($first)->toMatch('/^[a-f0-9]{64}$/')
        ->and($second)->not->toBe($first);
});

it('ten sam adres daje ten sam skrót przy tym samym kluczu (normalizacja działa)', function () {
    $a = new AddressQuery(street: 'Polna', city: 'Dąbrówka');
    $b = new AddressQuery(street: 'POLNA', city: 'dabrowka');

    expect($a->hash())->toBe($b->hash());
});

it('nie podąża za przekierowaniem odpowiedzi Nominatim', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://wewnetrzny.example/'])]);

    $result = app(NominatimProvider::class)->search(new AddressQuery(city: 'Dąbrówka'));

    expect($result->status)->toBe(GeocodeStatus::Unavailable);
    Http::assertSentCount(1);
});

it('odrzuca odpowiedź większą niż 64 KB jako błędną', function () {
    Http::fake(['*' => Http::response(json_encode([['lat' => '52.3', 'lon' => '16.6', 'pad' => str_repeat('x', 70000)]]), 200)]);

    $result = app(NominatimProvider::class)->search(new AddressQuery(city: 'Dąbrówka'));

    expect($result->status)->toBe(GeocodeStatus::Unavailable)
        ->and($result->reason)->toBe('bad_response');
});

it('przyjmuje zwykłą, małą odpowiedź', function () {
    Http::fake(['*' => Http::response([['lat' => '52.3', 'lon' => '16.6', 'addresstype' => 'village']], 200)]);

    $result = app(NominatimProvider::class)->search(new AddressQuery(city: 'Dąbrówka'));

    expect($result->status)->toBe(GeocodeStatus::Found);
});

it('strażnik produkcji wymaga https dla NOMINATIM_URL', function () {
    config(['geocoding.base_url' => 'http://nominatim.example']);
    $insecure = (new ProductionConfigGuard)->violations();

    config(['geocoding.base_url' => 'https://nominatim.example']);
    $secure = (new ProductionConfigGuard)->violations();

    expect(implode(' ', $insecure))->toContain('NOMINATIM_URL')
        ->and(implode(' ', $secure))->not->toContain('NOMINATIM_URL');
});

it('trafienie dla adresu z ulicą żyje 30 dni, a dla samej miejscowości 180 dni', function () {
    Http::fake(['*' => Http::response([['lat' => '52.31', 'lon' => '16.62', 'addresstype' => 'house']], 200)]);
    $service = app(GeocodingService::class);

    $service->geocodeAddress(new AddressQuery(street: 'Polna', houseNumber: '12', city: 'Dąbrówka', simc: '0900001'));
    $withStreet = GeocodeCacheEntry::query()->first();

    GeocodeCacheEntry::query()->delete();
    Cache::flush();

    $service->geocodeAddress(new AddressQuery(city: 'Dąbrówka', simc: '0900001'));
    $withoutStreet = GeocodeCacheEntry::query()->first();

    expect((int) round(now()->diffInDays($withStreet->expires_at)))->toBe(30)
        ->and((int) round(now()->diffInDays($withoutStreet->expires_at)))->toBe(180);
});
