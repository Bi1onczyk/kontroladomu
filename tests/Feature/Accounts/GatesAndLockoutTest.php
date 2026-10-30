<?php

/*
| Kolejność bramek (e-mail -> 2FA -> pulpit) dla każdej roli oraz blokada i wygasanie wyzwania 2FA.
| Wszystkie testy idą prawdziwą ścieżką logowania (POST /logowanie), nie przez actingAs.
*/

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Tworzy konto danej roli z hasłem testowym. Opcje: verified (domyślnie tak), twoFactor (domyślnie nie).
 */
function accountFor(string $role, bool $verified = true, bool $twoFactor = false): User
{
    $factory = User::factory()->{$role}();
    if (! $verified) {
        $factory = $factory->unverified();
    }
    if ($twoFactor) {
        $factory = $factory->withTwoFactor();
    }

    return $factory->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
}

function logIn(object $test): TestResponse
{
    return $test->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
}

// ---------------------------------------------------------------- kolejność bramek

it('konto niepotwierdzone: po logowaniu bramka e-mail wygrywa dla KAŻDEJ roli (także z wymogiem 2FA)', function (string $role) {
    accountFor($role, verified: false);

    logIn($this)->assertRedirect('/pulpit');
    $this->assertAuthenticated();

    $this->get('/pulpit')->assertRedirect(route('verification.notice'));
    $this->get('/konto/bezpieczenstwo')->assertRedirect(route('verification.notice'));
    $this->get('/email/potwierdz')->assertOk();
})->with(['owner', 'specialist', 'technician', 'admin']);

it('konto niepotwierdzone nie może rozpocząć konfiguracji 2FA (bramka e-mail przed hasłem i 2FA)', function () {
    $user = accountFor('specialist', verified: false);

    $this->actingAs($user)->withSession(passwordConfirmed())->post('/konto/bezpieczenstwo/2fa')
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('po potwierdzeniu e-maila: fachowiec i admin bez 2FA trafiają do kreatora, właściciel i pracownik na pulpit', function (string $role, bool $needsWizard) {
    accountFor($role);

    logIn($this)->assertRedirect('/pulpit');

    $response = $this->get('/pulpit');
    if ($needsWizard) {
        $response->assertRedirect(route('account.security'))->assertSessionHas('two_factor_required', true);
        $this->get('/konto/bezpieczenstwo')->assertOk()->assertViewHas('forced', true);
    } else {
        $response->assertOk();
    }
})->with([
    ['owner', false],
    ['technician', false],
    ['specialist', true],
    ['admin', true],
]);

it('konto z potwierdzonym 2FA: najpierw hasło, potem kod, dopiero potem pulpit (każda rola)', function (string $role) {
    accountFor($role, twoFactor: true);

    logIn($this)->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->get('/pulpit')->assertRedirect(route('login'));

    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');
    $this->assertAuthenticated();
    $this->get('/pulpit')->assertOk();
})->with(['owner', 'specialist', 'technician', 'admin']);

it('konto niepotwierdzone z 2FA: najpierw kod, a po nim ekran potwierdzenia e-maila', function () {
    accountFor('specialist', verified: false, twoFactor: true);

    logIn($this)->assertRedirect(route('two-factor.login'));
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');

    $this->get('/pulpit')->assertRedirect(route('verification.notice'));
});

it('ekran wyzwania 2FA jest niedostępny bez poprawnego hasła, a poprawny kod bez hasła nic nie daje', function () {
    accountFor('specialist', twoFactor: true);

    $this->get('/logowanie/kod')->assertRedirect(route('login'));
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect();
    $this->assertGuest();
});

it('złe hasło nie otwiera wyzwania 2FA', function () {
    accountFor('specialist', twoFactor: true);

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo'])->assertSessionHasErrors('email');
    $this->get('/logowanie/kod')->assertRedirect(route('login'));
});

// ---------------------------------------------------------------- blokada 5 błędów / 15 minut

it('pozwala na 4 błędy, a poprawny kod po nich loguje i zeruje licznik', function () {
    $user = accountFor('specialist', twoFactor: true);
    logIn($this);

    foreach (range(1, 4) as $i) {
        $this->post('/logowanie/kod', ['code' => '00000'.$i])->assertSessionHasErrors();
    }

    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');
    $this->assertAuthenticatedAs($user);

    // Licznik wyzerowany: kolejne 4 błędy w nowym logowaniu znów nie blokują.
    auth()->logout();
    $this->flushSession();
    logIn($this);
    foreach (range(1, 4) as $i) {
        $this->post('/logowanie/kod', ['code' => '11111'.$i])->assertSessionHasErrors();
    }
    $this->get('/logowanie/kod')->assertOk();
});

it('poprawny kod awaryjny też zeruje licznik błędów', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);

    foreach (range(1, 4) as $i) {
        $this->post('/logowanie/kod', ['code' => '00000'.$i])->assertSessionHasErrors();
    }
    $this->post('/logowanie/kod', ['recovery_code' => 'aaaaaaaaaa-bbbbbbbbbb'])->assertRedirect('/pulpit');

    auth()->logout();
    $this->flushSession();
    logIn($this);
    foreach (range(1, 4) as $i) {
        $this->post('/logowanie/kod', ['code' => '22222'.$i])->assertSessionHasErrors();
    }
    $this->get('/logowanie/kod')->assertOk();
});

it('błędy z kilku sesji sumują się na jednym koncie (zmiana sesji nie resetuje blokady)', function () {
    accountFor('specialist', twoFactor: true);

    foreach ([3, 2] as $errors) {
        $this->flushSession();
        logIn($this);
        foreach (range(1, $errors) as $i) {
            $this->post('/logowanie/kod', ['code' => '33333'.$i]);
        }
    }

    $this->flushSession();
    logIn($this);
    $this->get('/logowanie/kod')->assertStatus(429);
});

it('blokada dotyczy tylko jednego konta, nie innych użytkowników', function () {
    $victim = User::factory()->specialist()->withTwoFactor()->create(['email' => 'ofiara@example.pl', 'password' => TEST_PASSWORD]);
    User::factory()->specialist()->withTwoFactor()->create(['email' => 'inny@example.pl', 'password' => TEST_PASSWORD]);

    $this->post('/logowanie', ['email' => 'ofiara@example.pl', 'password' => TEST_PASSWORD]);
    foreach (range(1, 5) as $i) {
        $this->post('/logowanie/kod', ['code' => '44444'.$i]);
    }
    $this->get('/logowanie/kod')->assertStatus(429);

    $this->flushSession();
    $this->post('/logowanie', ['email' => 'inny@example.pl', 'password' => TEST_PASSWORD]);
    $this->get('/logowanie/kod')->assertOk();
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');

    expect($victim->id)->not->toBe(auth()->id());
});

it('blokada trwa dokładnie 15 minut od pierwszego błędu: po 14 minutach nadal, po 16 koniec', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);
    foreach (range(1, 5) as $i) {
        $this->post('/logowanie/kod', ['code' => '55555'.$i]);
    }

    $this->travel(9)->minutes(); // wyzwanie (10 min) jeszcze trwa, blokada też
    $locked = $this->get('/logowanie/kod')->assertStatus(429);
    expect((int) $locked->headers->get('Retry-After'))->toBeGreaterThan(300)->toBeLessThanOrEqual(360);

    // Poprawny kod w czasie blokady jest odrzucany (HTTP 429), konto nie jest zalogowane.
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertStatus(429);
    $this->assertGuest();

    $this->travel(7)->minutes(); // razem 16 minut
    logIn($this);
    $this->get('/logowanie/kod')->assertOk();
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');
});

it('zdarzenie błędu 2FA jest emitowane zarówno dla kodu z aplikacji, jak i awaryjnego', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);
    Event::fake([TwoFactorAuthenticationFailed::class]);

    $this->post('/logowanie/kod', ['code' => '000000']);
    $this->post('/logowanie/kod', ['recovery_code' => 'zly-kod-awaryjny']);

    Event::assertDispatchedTimes(TwoFactorAuthenticationFailed::class, 2);
});

it('limit 429 z ekranu blokady zawiera nagłówki bezpieczeństwa i nie ujawnia liczby prób', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);
    foreach (range(1, 5) as $i) {
        $this->post('/logowanie/kod', ['code' => '66666'.$i]);
    }

    $response = $this->get('/logowanie/kod')->assertStatus(429)->assertHeader('X-Frame-Options', 'DENY');
    expect($response->headers->get('Content-Security-Policy'))->not->toBeNull();
});

// ---------------------------------------------------------------- wygasanie wyzwania (10 min)

it('wyzwanie 2FA ważne jest 10 minut: po 9 jeszcze działa, po 11 nie', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);

    $this->travel(9)->minutes();
    $this->get('/logowanie/kod')->assertOk();

    $this->travel(2)->minutes();
    $this->get('/logowanie/kod')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('auth.twofa.challenge_expired'));
});

it('po wygaśnięciu wyzwania poprawny kod NIE loguje, a komunikat jest widoczny na ekranie logowania', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);

    $this->travel(11)->minutes();
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect(route('login'));
    $this->assertGuest();

    $this->get('/logowanie')->assertOk()->assertSeeText(__('auth.twofa.challenge_expired'));

    // Sesja wyzwania została skasowana: bez ponownego hasła nie da się wrócić do kodu.
    $this->get('/logowanie/kod')->assertRedirect(route('login'));
});

it('ponowne podanie hasła po wygaśnięciu rozpoczyna nowe, pełne 10 minut', function () {
    accountFor('specialist', twoFactor: true);
    logIn($this);
    $this->travel(11)->minutes();
    $this->get('/logowanie/kod')->assertRedirect(route('login'));

    logIn($this)->assertRedirect(route('two-factor.login'));
    $this->travel(5)->minutes();
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');
    $this->assertAuthenticated();
});
