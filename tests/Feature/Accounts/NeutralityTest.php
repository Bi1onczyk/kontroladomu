<?php

/*
| Neutralność odpowiedzi (brak enumeracji kont): logowanie, reset hasła, rejestracja.
| Porównujemy odpowiedzi i liczbę zapytań do bazy dla konta istniejącego i nieistniejącego.
| Różnica czasu odpowiedzi jest dodatkowo wyrównana przez Hash::make (patrz kod); tu mierzymy zapytania.
*/

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('logowanie: ta sama liczba zapytań dla istniejącego (złe hasło) i nieistniejącego konta', function () {
    User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $known = countQueries(fn () => $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo']));
    $this->flushSession();
    $unknown = countQueries(fn () => $this->post('/logowanie', ['email' => 'brak@example.pl', 'password' => 'zle-haslo-zle-haslo']));

    expect($known)->toBe($unknown);
});

it('logowanie: istniejące konto z włączonym 2FA i złym hasłem nie różni się odpowiedzią od nieistniejącego', function () {
    User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $a = $this->from('/logowanie')->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo']);
    $this->flushSession();
    $b = $this->from('/logowanie')->post('/logowanie', ['email' => 'brak@example.pl', 'password' => 'zle-haslo-zle-haslo']);

    expect($a->headers->get('Location'))->toBe($b->headers->get('Location'))
        ->and($a->getStatusCode())->toBe($b->getStatusCode());
});

it('logowanie: wielkość liter i ogonki w adresie nie powodują 500 ani innej odpowiedzi', function (string $email) {
    $this->post('/logowanie', ['email' => $email, 'password' => 'zle-haslo-zle-haslo'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
})->with(['ŻÓŁĆ@example.pl', 'JAN@EXAMPLE.PL', "jan'--@example.pl", "' OR 1=1 --", '<script>alert(1)</script>', str_repeat('a', 400).'@example.pl']);

it('logowanie: tablica zamiast tekstu w polach nie kończy się błędem 500', function () {
    $response = $this->post('/logowanie', ['email' => ['a@b.pl'], 'password' => ['x']]);

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('reset hasła: tablica zamiast adresu nie kończy się błędem 500', function () {
    $response = $this->post('/nie-pamietam-hasla', ['email' => ['a@b.pl']]);

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rejestracja: tablica zamiast adresu lub NIP nie kończy się błędem 500', function () {
    expect($this->post('/rejestracja/wlasciciel', ownerPayload(['email' => ['a@b.pl']]))->getStatusCode())->toBeLessThan(500);
    expect($this->post('/rejestracja/fachowiec', specialistPayload(['nip' => ['6931171824']]))->getStatusCode())->toBeLessThan(500);
    expect($this->post('/rejestracja/wlasciciel', ownerPayload(['name' => ['x']]))->getStatusCode())->toBeLessThan(500);
});

it('reset hasła: zbliżona liczba zapytań dla istniejącego i nieistniejącego konta (różnica najwyżej 4)', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'jan@example.pl']);

    $known = countQueries(fn () => $this->post('/nie-pamietam-hasla', ['email' => 'jan@example.pl']));
    $this->flushSession();
    $unknown = countQueries(fn () => $this->post('/nie-pamietam-hasla', ['email' => 'brak@example.pl']));

    expect(abs($known - $unknown))->toBeLessThanOrEqual(4);
});

it('reset hasła: to samo 429 po przekroczeniu limitu 5/godz. z jednego IP dla istniejącego i nieistniejącego adresu', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'jan@example.pl']);

    // Limit po adresie e-mail został usunięty (audyt S2: pozwalał blokować cudzy reset); zostaje limit po IP.
    foreach (['jan@example.pl' => '10.0.0.1', 'brak@example.pl' => '10.0.0.2'] as $email => $ip) {
        $client = $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        foreach (range(1, 5) as $i) {
            $client->post('/nie-pamietam-hasla', ['email' => $email])->assertRedirect();
        }
        $client->post('/nie-pamietam-hasla', ['email' => $email])->assertStatus(429);
    }
});

it('reset hasła: błąd przy złym tokenie jest taki sam dla istniejącego i nieistniejącego adresu', function () {
    User::factory()->owner()->create(['email' => 'jan@example.pl']);

    $payload = fn (string $email) => ['token' => 'zly-token', 'email' => $email, 'password' => 'nowe-zielone-haslo-123'];

    $known = $this->post('/nowe-haslo', $payload('jan@example.pl'));
    $knownError = session('errors')?->first('email');
    $this->flushSession();
    $unknown = $this->post('/nowe-haslo', $payload('brak@example.pl'));
    $unknownError = session('errors')?->first('email');

    expect($knownError)->not->toBeNull()
        ->and($unknownError)->toBe($knownError);
    expect($known->getStatusCode())->toBe($unknown->getStatusCode());
});

it('reset hasła: link jednorazowy - po użyciu token nie działa drugi raz', function () {
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl']);
    $token = Password::createToken($user);
    $payload = ['token' => $token, 'email' => 'jan@example.pl', 'password' => 'nowe-zielone-haslo-123'];

    $this->post('/nowe-haslo', $payload)->assertRedirect(route('login'));
    $this->post('/nowe-haslo', array_merge($payload, ['password' => 'jeszcze-inne-haslo-456']))->assertSessionHasErrors('email');

    expect(Hash::check('nowe-zielone-haslo-123', $user->fresh()->password))->toBeTrue();
});

it('rejestracja: zajęty e-mail nie zmienia istniejącego konta (hasło, rola, zgody)', function () {
    Notification::fake();
    $existing = User::factory()->owner()->create(['email' => 'anna@example.pl', 'password' => TEST_PASSWORD]);
    $hash = $existing->password;

    $this->post('/rejestracja/fachowiec', specialistPayload(['password' => 'calkiem-inne-haslo-999', 'marketing' => '1']));

    $existing->refresh();
    expect($existing->password)->toBe($hash)
        ->and($existing->hasRole('specialist'))->toBeFalse()
        ->and($existing->marketing_consent_at)->toBeNull()
        ->and($existing->nip)->toBeNull()
        ->and(User::count())->toBe(1);
});

it('rejestracja: różnica liczby zapytań między wolnym a zajętym adresem jest ograniczona (informacja o wyniku pomiaru)', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);

    $taken = countQueries(fn () => $this->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'zajety@example.pl'])));
    $this->flushSession();
    $free = countQueries(fn () => $this->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'wolny@example.pl'])));

    // Wolny adres tworzy konto (wiele INSERT-ów), zajęty - nie. Test pilnuje, by różnica nie rosła bez kontroli.
    expect($free - $taken)->toBeLessThanOrEqual(15);
});

it('rejestracja: zajęty NIP nie ujawnia istnienia innego konta (odpowiedź i powiadomienia)', function () {
    Notification::fake();
    $other = User::factory()->specialist()->create(['email' => 'inny@example.pl']);

    $response = $this->post('/rejestracja/fachowiec', specialistPayload(['email' => 'nowy@example.pl']));

    $response->assertRedirect(route('register.sent'))->assertSessionHasNoErrors();
    Notification::assertNothingSentTo($other);
    expect(User::where('email', 'nowy@example.pl')->exists())->toBeTrue();
});

it('weryfikacja e-mail: nieistniejące id i cudzy hash dają ten sam wynik "nieważny"', function () {
    $user = User::factory()->owner()->unverified()->create();

    $url = fn (int $id, string $hash) => URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $id, 'hash' => $hash]);

    $a = $this->get($url(999999, sha1('x')));
    $this->flushSession();
    $b = $this->get($url($user->id, sha1('cudzy')));

    $a->assertRedirect(route('verification.done'))->assertSessionHas('verify_status', 'invalid');
    $b->assertRedirect(route('verification.done'))->assertSessionHas('verify_status', 'invalid');
});
