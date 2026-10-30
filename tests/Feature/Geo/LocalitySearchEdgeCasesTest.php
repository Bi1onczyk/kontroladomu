<?php

/*
| Przypadki brzegowe endpointu autouzupełniania miejscowości (tester, zadanie 003).
| Dane: SYNTETYCZNE fixtury z tests/Fixtures/teryt (24 miejscowości). Uzupełnia LocalitySearchTest.
*/

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    importTerytFixture();
    RateLimiter::clear('locality-search');
});

afterEach(function () {
    // Wyłączenie ochrony przed leniwym ładowaniem, żeby nie wpłynęło na inne testy.
    Model::preventLazyLoading(false);
});

/** Dodaje do bazy N miejscowości o nazwach "Prefiks 1..N" jednym zapytaniem na porcję (szybko, bez fabryk). */
function bulkLocalities(string $prefix, int $count): void
{
    $municipalityId = (int) TerytMunicipality::query()->value('id');
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, $count), 500) as $chunk) {
        DB::table('teryt_localities')->insert(array_map(fn (int $i): array => [
            'simc' => (string) (7000000 + $i),
            'municipality_id' => $municipalityId,
            'name' => "{$prefix} {$i}",
            'name_search' => strtolower($prefix).' '.$i,
            'kind_code' => '01',
            'kind' => 'village',
            'is_part' => false,
            'slug' => strtolower($prefix).'-'.$i,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }
}

it('znajduje nazwy z różnymi polskimi znakami po wpisaniu ich w uproszczonej i oryginalnej formie', function (string $q, string $expectedName) {
    $names = $this->getJson('/miejscowosci/szukaj?q='.urlencode($q))->assertOk()->json('data.*.name');

    expect($names)->toContain($expectedName);
})->with([
    'ł -> l' => ['laczki', 'Łączki-Zagórze'],
    'Ł wielka z kreską' => ['ŁĄCZKI', 'Łączki-Zagórze'],
    'myślnik jako spacja' => ['łączki zag', 'Łączki-Zagórze'],
    'myślnik wpisany' => ['Łączki-Zag', 'Łączki-Zagórze'],
    'ó i ń' => ['kornik', 'Kórnik'],
    'ż i ź w środku' => ['blazejewko', 'Błażejewko'],
    'poznan bez ń' => ['poznan', 'Poznań'],
]);

it('akceptuje zapytanie z dużą liczbą spacji i zwija je do jednej', function () {
    $names = $this->getJson('/miejscowosci/szukaj?q='.urlencode('nowy      dw'))->assertOk()->json('data.*.name');

    expect($names)->toBe(['Nowy Dwór']);
});

it('zapytanie o 1 znak po obcięciu spacji kończy się błędem 422, a o 2 znaki jest poprawne', function () {
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('  a  '))->assertStatus(422)->assertJsonValidationErrors('q');
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('  ko  '))->assertOk();
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('   '))->assertStatus(422);
});

it('zapytanie złożone z samej interpunkcji jest poprawne, ale nic nie zwraca (bez błędu 500)', function () {
    foreach (['..', '--', ',,', "''"] as $q) {
        $this->getJson('/miejscowosci/szukaj?q='.urlencode($q))->assertOk()->assertExactJson(['data' => []]);
    }
});

it('odrzuca q podane jako tablica, pusty tekst i brak parametru', function (string $url) {
    $this->get($url)->assertStatus(422)->assertJsonValidationErrors('q');
})->with([
    'tablica' => ['/miejscowosci/szukaj?q[]=ko'],
    'tablica asocjacyjna' => ['/miejscowosci/szukaj?q[a]=ko'],
    'pusty' => ['/miejscowosci/szukaj?q='],
]);

it('wstrzyknięcia SQL i HTML nie przechodzą walidacji lub nie dają wyników, a tabela pozostaje nienaruszona', function (string $q) {
    $before = TerytLocality::query()->count();

    $response = $this->get('/miejscowosci/szukaj?q='.urlencode($q));

    expect($response->status())->toBeIn([200, 422])
        ->and(TerytLocality::query()->count())->toBe($before);

    if ($response->status() === 200) {
        expect($response->json('data'))->toBe([]);
    }
})->with([
    'klasyk' => ["' OR 1=1 --"],
    'średnik i DROP' => ["'; DROP TABLE teryt_localities; --"],
    'sam apostrof z OR' => ["' OR '1"],
    'union' => ["x' UNION SELECT * FROM users --"],
    'script' => ['<script>alert(1)</script>'],
    'img onerror' => ['<img src=x onerror=alert(1)>'],
    'procent i podkreślnik' => ['%_%'],
    'ukośnik' => ['ko\\'],
]);

it('odrzuca emoji i znaki sterujące w zapytaniu', function (string $q) {
    $this->get('/miejscowosci/szukaj?q='.urlencode($q))->assertStatus(422)->assertJsonValidationErrors('q');
})->with([
    'emoji' => ["ko\u{1F600}"],
    'tabulator w środku' => ["ko\tko"],
    'nowa linia w środku' => ["ko\nko"],
]);

it('liczy długość zapytania w znakach, a nie w bajtach (60 polskich liter jest poprawne, 61 nie)', function () {
    $this->getJson('/miejscowosci/szukaj?q='.urlencode(str_repeat('ą', 60)))->assertOk();
    $this->getJson('/miejscowosci/szukaj?q='.urlencode(str_repeat('ą', 61)))->assertStatus(422);
});

it('granice parametru limit: 1 i 15 są poprawne, puste daje wartość domyślną', function () {
    $this->getJson('/miejscowosci/szukaj?q=dabrowka&limit=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/miejscowosci/szukaj?q=dabrowka&limit=15')->assertOk()->assertJsonCount(6, 'data');
    $this->getJson('/miejscowosci/szukaj?q=dabrowka&limit=')->assertOk()->assertJsonCount(6, 'data');
});

it('odrzuca niepoprawny limit (ujemny, ułamek, powyżej 15, zapis naukowy, przepełnienie, tablica)', function (string $limit) {
    $this->get('/miejscowosci/szukaj?q=dabrowka&limit='.$limit)->assertStatus(422)->assertJsonValidationErrors('limit');
})->with(['-1', '1.5', '16', '1e1', '99999999999999999999', '%5B%5D=3']);

it('domyślny limit to 10, a maksymalny 15 przy większej liczbie dopasowań', function () {
    bulkLocalities('Testowo', 30);

    expect($this->getJson('/miejscowosci/szukaj?q=testowo')->assertOk()->json('data'))->toHaveCount(10);
    expect($this->getJson('/miejscowosci/szukaj?q=testowo&limit=15')->assertOk()->json('data'))->toHaveCount(15);
});

it('include_parts przyjmuje 0 i 1; 0 ukrywa części, 1 pokazuje', function () {
    $without = $this->getJson('/miejscowosci/szukaj?q=dabrowka&include_parts=0')->assertOk()->json('data.*.name');
    $with = $this->getJson('/miejscowosci/szukaj?q=dabrowka&include_parts=1')->assertOk()->json('data.*.name');

    expect($without)->not->toContain('Dąbrówka-Osiedle')
        ->and($with)->toContain('Dąbrówka-Osiedle')
        ->and($with)->toHaveCount(count($without) + 1);
});

it('include_parts działa także w zapytaniu "miejscowość gmina"', function () {
    $query = '/miejscowosci/szukaj?q='.urlencode('dabrowka dopiewo');

    $without = $this->getJson($query)->assertOk()->json('data.*.name');
    $with = $this->getJson($query.'&include_parts=1')->assertOk()->json('data.*.name');

    expect($without)->not->toContain('Dąbrówka-Osiedle')
        ->and($with)->toContain('Dąbrówka-Osiedle');
});

it('części miejscowości są domyślnie ukryte także dla przysiółków i części miast', function () {
    // Łazarz i Jeżyce to części miasta Poznań (RM 99), domyślnie niewidoczne.
    $names = $this->getJson('/miejscowosci/szukaj?q=lazarz')->assertOk()->json('data');
    $withParts = $this->getJson('/miejscowosci/szukaj?q=lazarz&include_parts=1')->assertOk()->json('data.*.name');

    expect($names)->toBe([])->and($withParts)->toBe(['Łazarz']);
});

it('nie powtarza tej samej miejscowości w wynikach (dopasowanie po nazwie i po gminie)', function () {
    $simcs = $this->getJson('/miejscowosci/szukaj?q='.urlencode('dopiewo dopiewo'))->assertOk()->json('data.*.simc');
    $simcs2 = $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka d'))->assertOk()->json('data.*.simc');

    expect($simcs)->toBe(array_values(array_unique($simcs)))
        ->and($simcs2)->toBe(array_values(array_unique($simcs2)));
});

it('limit obowiązuje również w drugim kroku wyszukiwania (miejscowość + powiat)', function () {
    $simcs = $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka poznanski').'&limit=2')->assertOk()->json('data.*.simc');

    expect($simcs)->toHaveCount(2);
});

it('kolejność wyników jest deterministyczna (to samo zapytanie, ten sam porządek)', function () {
    $first = $this->getJson('/miejscowosci/szukaj?q=dabrowka')->json('data.*.simc');
    $second = $this->getJson('/miejscowosci/szukaj?q=dabrowka')->json('data.*.simc');

    expect($first)->toBe($second);
});

it('wieś o tej samej nazwie w kilku gminach da się odróżnić po etykiecie', function () {
    $items = $this->getJson('/miejscowosci/szukaj?q=dabrowka')->assertOk()->json('data');

    // Sześć Dąbrówek: każda ma osobny kod SIMC i osobny slug, a etykiety zawierają gminę.
    // Dwie z nich leżą w tej samej gminie Dopiewo (fixtura celowo tworzy kolizję), więc etykiety mogą się powtarzać.
    expect($items)->toHaveCount(6)
        ->and(collect($items)->pluck('simc')->unique())->toHaveCount(6)
        ->and(collect($items)->pluck('slug')->unique())->toHaveCount(6)
        ->and(collect($items)->pluck('label')->unique()->count())->toBeGreaterThanOrEqual(4);
});

it('odpowiada tak samo gościowi i zalogowanemu użytkownikowi (dane publiczne, bez 403)', function () {
    $guest = $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk()->json('data');

    $user = User::factory()->create();
    $loggedIn = $this->actingAs($user)->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk()->json('data');

    expect($loggedIn)->toBe($guest);
});

it('inne metody HTTP niż GET są odrzucane (405), a nie obsługiwane', function (string $method) {
    $this->call($method, '/miejscowosci/szukaj', ['q' => 'ko'])->assertStatus(405);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('limit żądań dotyczy adresu IP: inny adres nie jest blokowany', function () {
    $this->freezeTime();

    foreach (range(1, 9) as $i) {
        $this->getJson('/miejscowosci/szukaj?q=dopiewo');
    }
    $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->getJson('/miejscowosci/szukaj?q=dopiewo')
        ->assertOk();
});

it('po upływie sekundy limit 8/s znowu przepuszcza żądania', function () {
    $this->freezeTime();

    foreach (range(1, 9) as $i) {
        $this->getJson('/miejscowosci/szukaj?q=dopiewo');
    }
    $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertStatus(429);

    $this->travel(2)->seconds();

    $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk();
});

it('nie ładuje relacji leniwie (preventLazyLoading nie zgłasza wyjątku przy wielu wynikach)', function () {
    Model::preventLazyLoading(true);

    $response = $this->getJson('/miejscowosci/szukaj?q=dabrowka&include_parts=1&limit=15');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(7);

    // Drugi krok wyszukiwania ("miejscowość gmina") również ładuje gminy z góry.
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka poznanski'))->assertOk();
});

it('liczba zapytań SQL nie zależy od liczby wyników także w trybie "miejscowość gmina"', function () {
    $few = countQueries(fn () => $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka dopiewo').'&limit=1'));
    $many = countQueries(fn () => $this->getJson('/miejscowosci/szukaj?q='.urlencode('dabrowka poznanski').'&limit=15'));

    // Maksymalnie: zapytanie podstawowe + gmina (with) + drugi krok + gmina drugiego kroku.
    expect($many)->toBeLessThanOrEqual(4)->and($few)->toBeLessThanOrEqual(4);
});

it('odpowiada szybko na bazie z kilkoma tysiącami miejscowości (poniżej 500 ms)', function () {
    bulkLocalities('Kowalewo', 5000);

    $startedAt = microtime(true);
    $this->getJson('/miejscowosci/szukaj?q=ko&limit=15')->assertOk();
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('kowalewo 12'))->assertOk();
    $this->getJson('/miejscowosci/szukaj?q='.urlencode('ko dopiewo'))->assertOk();
    $elapsedMs = (microtime(true) - $startedAt) * 1000;

    expect($elapsedMs)->toBeLessThan(500);
});

it('odpowiedź JSON ma poprawny typ zawartości i nie zawiera pól wewnętrznych', function () {
    $response = $this->getJson('/miejscowosci/szukaj?q=dopiewo')->assertOk();
    $item = $response->json('data.0');

    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and(array_keys($item))->not->toContain('id')
        ->and(array_keys($item))->not->toContain('municipality_id')
        ->and(array_keys($item))->not->toContain('parent_simc')
        ->and(array_keys($item))->not->toContain('is_active');
});

it('odpowiedzi błędów nie są publicznie cache\'owane', function () {
    $response = $this->get('/miejscowosci/szukaj?q=a');

    expect($response->status())->toBe(422)
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

it('bajt zerowy w zapytaniu nie powoduje błędu serwera (jest obcinany jak biały znak)', function () {
    $response = $this->get('/miejscowosci/szukaj?q='.urlencode("ko\0"));

    expect($response->status())->toBeIn([200, 422]);
});

it('parametr limit złożony z samych spacji jest traktowany jak pusty (wartość domyślna)', function () {
    $this->getJson('/miejscowosci/szukaj?q=dabrowka&limit=%20%20')->assertOk()->assertJsonCount(6, 'data');
});
