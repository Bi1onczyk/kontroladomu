<?php

/*
| Testy z PRAWDZIWYMI widokami Blade (bez podstawiania atrap).
| Cel: żadna trasa publiczna ani panelowa nie kończy się błędem 500 i żadna strona
| nie pokazuje surowych kluczy tłumaczeń (np. "auth.login.title").
*/

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Zwraca widoczny tekst strony (bez znaczników i skryptów), żeby szukać w nim surowych kluczy lang.
 */
function visibleText(string $html): string
{
    $html = preg_replace('#<(script|style)\b.*?</\1>#si', ' ', $html) ?? $html;

    return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sprawdza, że w tekście nie ma niezałatwionych kluczy tłumaczeń.
 */
function expectNoRawTranslationKeys(string $html, string $where): void
{
    $text = visibleText($html);
    $found = preg_match('/\b(?:auth|dashboard|pages|ui|mail|roles|passwords|validation)\.[a-z0-9_]+(?:\.[a-z0-9_]+)*\b/', $text, $m);

    expect($found)->toBe(0, "Surowy klucz tłumaczenia na stronie {$where}: ".($m[0] ?? ''));
}

dataset('strony publiczne', [
    'strona główna' => '/',
    'logowanie' => '/logowanie',
    'wybór typu konta' => '/rejestracja',
    'rejestracja właściciela' => '/rejestracja/wlasciciel',
    'rejestracja fachowca' => '/rejestracja/fachowiec',
    'sprawdź skrzynkę' => '/rejestracja/sprawdz-skrzynke',
    'nie pamiętam hasła' => '/nie-pamietam-hasla',
    'nowe hasło (link z maila)' => '/nowe-haslo/abc123?email=jan%40example.pl',
    'e-mail potwierdzony (bez flasha)' => '/email/potwierdzono',
    'regulamin' => '/regulamin',
    'polityka prywatności' => '/polityka-prywatnosci',
    'polityka cookies' => '/polityka-cookies',
    'kontakt' => '/kontakt',
    'health' => '/up',
]);

it('zwraca 200 i prawdziwy HTML dla strony publicznej dla gościa', function (string $url) {
    $response = $this->get($url);

    $response->assertOk();
    expectNoRawTranslationKeys($response->getContent(), $url);
})->with('strony publiczne');

it('strony publiczne mają <h1> i komplet nagłówków bezpieczeństwa', function (string $url) {
    if ($url === '/up') {
        $this->get($url)->assertOk();

        return;
    }

    $response = $this->get($url)->assertOk();
    expect(substr_count($response->getContent(), '<h1'))->toBeGreaterThanOrEqual(1);
    $response->assertHeader('Content-Security-Policy')->assertHeader('X-Frame-Options', 'DENY');
})->with('strony publiczne');

it('przekierowuje gościa z tras panelowych na logowanie', function (string $method, string $url) {
    $this->{$method}($url)->assertRedirect(route('login'));
})->with([
    ['get', '/pulpit'],
    ['get', '/konto/bezpieczenstwo'],
    ['get', '/email/potwierdz'],
    ['get', '/email/zmien'],
    ['get', '/potwierdz-haslo'],
    ['get', '/konto/bezpieczenstwo/kody-awaryjne.txt'],
    ['post', '/wyloguj'],
    ['post', '/konto/haslo'],
    ['post', '/konto/bezpieczenstwo/2fa'],
    ['delete', '/konto/bezpieczenstwo/2fa'],
    ['post', '/email/ponow'],
]);

it('wyzwanie 2FA bez rozpoczętego logowania przekierowuje na logowanie, a nie 500', function () {
    $this->get('/logowanie/kod')->assertRedirect(route('login'));
    // POST bez wyzwania: Fortify wraca na ekran kodu, a ten odsyła na logowanie (302, nigdy 500).
    $this->post('/logowanie/kod', ['code' => '123456'])->assertRedirect();
    $this->get('/logowanie/kod')->assertRedirect(route('login'));
});

it('pokazuje ekran wyzwania 2FA po poprawnym haśle (prawdziwy widok)', function () {
    User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $response = $this->get('/logowanie/kod')->assertOk();

    expect($response->getContent())->toContain('name="code"')->toContain('name="recovery_code"');
    expectNoRawTranslationKeys($response->getContent(), '/logowanie/kod');
});

it('pokazuje ekran blokady 2FA (429) z prawdziwym widokiem', function () {
    User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    foreach (range(1, 5) as $i) {
        $this->post('/logowanie/kod', ['code' => '00000'.$i]);
    }

    $response = $this->get('/logowanie/kod')->assertStatus(429)->assertHeader('Retry-After');
    expect($response->getContent())->toContain('role="alert"')->toContain('15 minut');
    expectNoRawTranslationKeys($response->getContent(), 'blokada 2FA');
});

it('renderuje ekran "e-mail potwierdzony" we wszystkich wariantach', function (string $status, ?string $role) {
    $flash = ['verify_status' => $status];
    if ($role !== null) {
        $flash['verified_role'] = $role;
    }

    $response = $this->withSession($flash)->get('/email/potwierdzono')->assertOk();
    expectNoRawTranslationKeys($response->getContent(), "verify-done {$status}");
    expect($response->getContent())->toContain(route('login'));
})->with([
    ['verified', 'owner'],
    ['verified', 'specialist'],
    ['already', null],
    ['invalid', null],
]);

it('renderuje "sprawdź skrzynkę" z adresem i bez adresu', function () {
    $with = $this->withSession(['registered_email' => 'anna@example.pl'])->get('/rejestracja/sprawdz-skrzynke')->assertOk();
    expect($with->getContent())->toContain('anna@example.pl');

    $this->flushSession();
    $without = $this->get('/rejestracja/sprawdz-skrzynke')->assertOk();
    expect($without->getContent())->not->toContain('anna@example.pl');
});

it('pokazuje widżet Turnstile na rejestracji i resecie, a na logowaniu dopiero po 3 błędach', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'klucz-strony', 'services.turnstile.secret' => 's']);

    foreach (['/rejestracja/wlasciciel', '/rejestracja/fachowiec', '/nie-pamietam-hasla'] as $url) {
        expect($this->get($url)->assertOk()->getContent())->toContain('cf-turnstile')->toContain('klucz-strony');
    }

    expect($this->get('/logowanie')->assertOk()->getContent())->not->toContain('cf-turnstile');

    User::factory()->owner()->create(['email' => 'jan@example.pl']);
    foreach (range(1, 3) as $i) {
        $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo']);
    }

    expect($this->get('/logowanie')->assertOk()->getContent())->toContain('cf-turnstile');
});

it('znaczniki <script> na stronach z Turnstile mają nonce zgodny z nagłówkiem CSP', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'k']);

    $response = $this->get('/rejestracja/wlasciciel')->assertOk();
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $csp);
    preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $scripts);

    expect($csp)->not->toBeEmpty()->and($scripts[0])->not->toBeEmpty();
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$csp[1].'"');
    }
});

// --- strefa zalogowanych

it('pulpit każdej roli zwraca 200 z prawdziwym widokiem i powitaniem', function (string $state) {
    $user = User::factory()->{$state}()->create(['name' => 'Zofia Żółć-Ćwik']);
    if (in_array($state, ['specialist', 'admin'], true)) {
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(array_fill(0, 8, 'aaaaaaaaaa-bbbbbbbbbb'))),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    $response = $this->actingAs($user)->get('/pulpit')->assertOk();

    expect($response->getContent())->toContain('Zofia Żółć-Ćwik')->toContain('<h1');
    expectNoRawTranslationKeys($response->getContent(), "/pulpit ({$state})");
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with(['owner', 'specialist', 'technician', 'admin']);

it('strony konta zwracają 200 dla zalogowanego właściciela', function (string $url) {
    $response = $this->actingAs(User::factory()->owner()->create())->get($url)->assertOk();
    expectNoRawTranslationKeys($response->getContent(), $url);
})->with(['/konto/bezpieczenstwo', '/potwierdz-haslo', '/regulamin', '/polityka-prywatnosci', '/polityka-cookies', '/kontakt', '/email/potwierdzono']);

it('zalogowany jest odsyłany z ekranów dla gości na pulpit', function (string $url) {
    $this->actingAs(User::factory()->owner()->create())->get($url)->assertRedirect(route('dashboard'));
})->with(['/logowanie', '/rejestracja', '/rejestracja/wlasciciel', '/nie-pamietam-hasla']);

it('konto niepotwierdzone: ekran potwierdzenia i zmiany adresu działają, panel jest zamknięty', function () {
    $user = User::factory()->owner()->unverified()->create(['email' => 'stary@example.pl']);

    $notice = $this->actingAs($user)->get('/email/potwierdz')->assertOk();
    expect($notice->getContent())->toContain('stary@example.pl');
    expectNoRawTranslationKeys($notice->getContent(), 'verify-notice');

    $change = $this->actingAs($user)->get('/email/zmien')->assertOk();
    expectNoRawTranslationKeys($change->getContent(), 'change-email');

    $this->actingAs($user)->get('/pulpit')->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertRedirect(route('verification.notice'));
});

it('potwierdzone konto wchodzące na /email/potwierdz trafia na pulpit', function () {
    $this->actingAs(User::factory()->owner()->create())->get('/email/potwierdz')->assertRedirect(route('dashboard'));
});

it('konto bez żadnej roli dostaje stronę 403, a nie 500', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/pulpit')->assertForbidden();
    expect($response->getContent())->toContain('<h1');
});

it('renderuje wszystkie kroki kreatora 2FA z prawdziwymi widokami', function () {
    $user = User::factory()->specialist()->create();

    // krok 1 - wprowadzenie, tryb wymuszony
    $intro = $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertOk();
    expect($intro->getContent())->toContain(route('two-factor.enable'));
    expectNoRawTranslationKeys($intro->getContent(), 'kreator krok 1');

    // krok 2 - QR i klucz
    $this->actingAs($user)->withSession(passwordConfirmed())->post('/konto/bezpieczenstwo/2fa');
    $scan = $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertOk();
    expect($scan->getContent())->toContain('data:image/svg+xml;base64,')->toContain(route('two-factor.confirm'));
    expectNoRawTranslationKeys($scan->getContent(), 'kreator krok 2');

    // krok 3 - kody awaryjne
    $this->actingAs($user)->withSession(passwordConfirmed())
        ->post('/konto/bezpieczenstwo/2fa/potwierdz', ['code' => currentTotp($user)]);
    $codes = $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertOk();
    foreach ($user->fresh()->recoveryCodes() as $code) {
        expect($codes->getContent())->toContain($code);
    }
    expectNoRawTranslationKeys($codes->getContent(), 'kreator krok 3');

    // zarządzanie po zakończeniu
    $manage = $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertOk();
    expect($manage->getContent())->not->toContain('data:image/svg+xml');
    expectNoRawTranslationKeys($manage->getContent(), 'zarządzanie 2FA');
});

it('strony błędów renderują się prawdziwym widokiem z odpowiednim kodem', function (int $code) {
    config(['app.debug' => false]);
    Route::get('/test-blad/'.$code, fn () => abort($code))->middleware('web');

    $response = $this->get('/test-blad/'.$code);

    if ($code === 419) {
        // 419 kierowane jest na logowanie z komunikatem (decyzja architekta), widok jest tylko rezerwą.
        $response->assertRedirect(route('login'));

        return;
    }

    $response->assertStatus($code);
    expect($response->getContent())->toContain('<h1')->toContain((string) $code);
    expectNoRawTranslationKeys($response->getContent(), "błąd {$code}");
})->with([403, 404, 419, 429, 500, 503]);

it('rezerwowa strona 419 renderuje się bez błędu', function () {
    $html = view('errors.419', ['exception' => new HttpException(419)])->render();

    expect($html)->toContain('<h1');
    expectNoRawTranslationKeys($html, 'errors.419');
});

it('strona 404 pokazuje link do pulpitu zalogowanym', function () {
    $response = $this->actingAs(User::factory()->owner()->create())->get('/nie-ma-takiej-strony')->assertNotFound();

    expect($response->getContent())->toContain(route('dashboard'));
});
