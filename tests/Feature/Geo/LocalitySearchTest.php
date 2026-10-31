<?php

use App\Domain\Geo\Actions\SearchLocalities;
use App\Domain\Geo\Models\TerytLocality;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    importTerytFixture();
});

it('znajduje miejscowość niezależnie od polskich znaków i wielkości liter', function (string $q) {
    $names = $this->getJson('/miejscowosci/szukaj?q='.urlencode($q))->assertOk()->json('data.*.name');

    // 6 Dąbrówek; część "Dąbrówka-Osiedle" jest domyślnie ukryta.
    expect($names)->toHaveCount(6)->each->toBe('Dąbrówka');
})->with(['dabrowka', 'Dąbrówka', 'DĄBRÓWKA', '  dąbr  ']);

it('pokazuje części miejscowości po przełączniku include_parts', function () {
    $names = $this->getJson('/miejscowosci/szukaj?q=dabrowka&include_parts=1')->assertOk()->json('data.*.name');

    expect($names)->toHaveCount(7)->toContain('Dąbrówka-Osiedle');
});

it('zwraca strukturę z kontraktu bez wewnętrznych id', function () {
    TerytLocality::query()->where('simc', '0900002')->update(['lat' => 52.3, 'lng' => 16.6]);

    $item = $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk()->json('data.0');

    expect($item)->toBe([
        'simc' => '0900002',
        'name' => 'Dopiewo',
        'label' => 'Dopiewo, gm. Dopiewo, pow. poznański, woj. wielkopolskie',
        'kind' => 'village',
        'kind_label' => 'wieś',
        'slug' => 'dopiewo',
        'municipality' => 'Dopiewo',
        'county' => 'poznański',
        'voivodeship' => 'wielkopolskie',
        'lat' => 52.3,
        'lng' => 16.6,
        'has_coordinates' => true,
    ]);
});

it('zwraca null w polach współrzędnych, gdy miejscowość nie była geokodowana', function () {
    $item = $this->getJson('/miejscowosci/szukaj?q=liszki')->assertOk()->json('data.0');

    expect($item['lat'])->toBeNull()->and($item['lng'])->toBeNull()->and($item['has_coordinates'])->toBeFalse();
});

it('na pierwszym miejscu stawia dokładne dopasowanie nazwy', function () {
    $names = $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk()->json('data.*.name');

    expect($names)->toBe(['Dopiewo', 'Dopiewo-Nowe']);
});

it('stawia miasta przed wsiami', function () {
    $names = $this->getJson('/miejscowosci/szukaj?q=ko')->assertOk()->json('data.*.name');

    expect($names)->toBe(['Kórnik', 'Konarskie']);
});

it('pozwala zawęzić wynik nazwą gminy albo powiatu', function () {
    $byMunicipality = $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka dopiewo'))->assertOk()->json('data.*.simc');
    $byCounty = $this->getJson('/miejscowosci/szukaj?q='.urlencode('Dąbrówka poznański'))->assertOk()->json('data.*.simc');

    expect($byMunicipality)->toEqualCanonicalizing(['0900001', '0900017', '0900018'])
        ->and($byCounty)->toEqualCanonicalizing(['0900001', '0900007', '0900017', '0900018']);
});

it('obsługuje nazwy wielowyrazowe z prefiksem', function () {
    $names = $this->getJson('/miejscowosci/szukaj?q='.urlencode('nowy dw'))->assertOk()->json('data.*.name');

    expect($names)->toBe(['Nowy Dwór']);
});

it('nie zwraca miejscowości wyłączonych (is_active = false)', function () {
    TerytLocality::query()->where('simc', '0900002')->update(['is_active' => false]);

    $names = $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk()->json('data.*.name');

    expect($names)->toBe(['Dopiewo-Nowe']);
});

it('respektuje limit i zwraca pustą listę, gdy nic nie pasuje', function () {
    $this->getJson('/miejscowosci/szukaj?q=dabrowka&limit=2')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/miejscowosci/szukaj?q=zzzzzz')->assertOk()->assertExactJson(['data' => []]);
});

it('znaki specjalne LIKE nie działają jak wzorzec', function () {
    // Bezpośrednio przez akcję (walidacja zatrzymałaby % wcześniej): po normalizacji zostaje "ko".
    $names = app(SearchLocalities::class)->handle('ko%', 10)->pluck('name')->all();

    expect($names)->toBe(['Kórnik', 'Konarskie']);
    expect(app(SearchLocalities::class)->handle('%%', 10))->toBeEmpty();
});

it('waliduje parametry i zawsze odpowiada JSON-em 422', function (string $url, string $field) {
    // Bez nagłówka Accept: Laravel nie może przekierować, ma zwrócić JSON.
    $this->get($url)->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'brak q' => ['/miejscowosci/szukaj', 'q'],
    'za krótkie q' => ['/miejscowosci/szukaj?q=a', 'q'],
    'niedozwolone znaki' => ['/miejscowosci/szukaj?q=ko%25%3Cscript%3E', 'q'],
    'limit zero' => ['/miejscowosci/szukaj?q=ko&limit=0', 'limit'],
    'limit za duży' => ['/miejscowosci/szukaj?q=ko&limit=16', 'limit'],
    'limit nie liczba' => ['/miejscowosci/szukaj?q=ko&limit=abc', 'limit'],
    'include_parts nie bool' => ['/miejscowosci/szukaj?q=ko&include_parts=tak', 'include_parts'],
]);

it('odrzuca zapytanie dłuższe niż 60 znaków', function () {
    $this->getJson('/miejscowosci/szukaj?q='.str_repeat('a', 61))->assertStatus(422)->assertJsonValidationErrors('q');
    $this->getJson('/miejscowosci/szukaj?q='.str_repeat('a', 60))->assertOk();
});

it('wysyła Cache-Control: public i nie ustawia ciasteczek ani sesji', function () {
    $response = $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600');
    expect($response->headers->getCookies())->toBe([]);
    expect($response->headers->has('Set-Cookie'))->toBeFalse();
});

it('nie wykonuje zapytań w pętli (stała liczba zapytań niezależnie od liczby wyników)', function () {
    $few = countQueries(fn () => $this->getJson('/miejscowosci/szukaj?q=dopiewo&limit=1'));
    $many = countQueries(fn () => $this->getJson('/miejscowosci/szukaj?q=dabrowka&limit=15'));

    expect($many)->toBe($few)->and($many)->toBeLessThanOrEqual(3);
});

it('ogranicza liczbę żądań do 8 na sekundę z IP i zwraca 429 z Retry-After', function () {
    $this->freezeTime();
    RateLimiter::clear('locality-search');

    foreach (range(1, 8) as $i) {
        $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk();
    }

    $this->getJson('/miejscowosci/szukaj?q=dopiewo')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJson(['message' => 'Zbyt wiele zapytań. Spróbuj za chwilę.']);
});

it('ma też limit 60 żądań na minutę', function () {
    $limits = RateLimiter::limiter('locality-search')(request());

    expect(collect($limits)->map(fn ($l) => [$l->maxAttempts, $l->decaySeconds])->all())
        ->toBe([[60, 60], [8, 1]]);
});
