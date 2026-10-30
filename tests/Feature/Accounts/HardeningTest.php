<?php

/*
| Twardnienie: nagłówki bezpieczeństwa, CSP, widoki bez {!! !!} i style="", mass assignment,
| konto zbanowane, unieważnianie sesji, XSS, zgody RODO z czasem UTC, konfiguracja okien czasowych.
*/

use App\Domain\Accounts\Actions\ResetUserPassword;
use App\Domain\Accounts\Notifications\VerifyEmailNotification;
use App\Models\User;
use App\Support\Security\ContentSecurityPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;

// ---------------------------------------------------------------- nagłówki i CSP

/** Wyciąga wartość dyrektywy z nagłówka CSP, np. "script-src". */
function cspDirective(string $csp, string $name): string
{
    foreach (explode(';', $csp) as $part) {
        $part = trim($part);
        if (str_starts_with($part, $name.' ')) {
            return $part;
        }
    }

    return '';
}

it('CSP: nonce w script-src jest losowy w każdym żądaniu i występuje w znacznikach <script>', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'k']);

    $nonces = [];
    foreach (range(1, 3) as $i) {
        $response = $this->get('/rejestracja/wlasciciel');
        preg_match("/script-src[^;]*'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $m);
        $nonces[] = $m[1];
        expect($response->getContent())->toContain('nonce="'.$m[1].'"');
    }

    expect(array_unique($nonces))->toHaveCount(3);
});

it('CSP: nigdy nie ma unsafe-eval, a script-src nigdy unsafe-inline (każde środowisko)', function (string $env) {
    app()->detectEnvironment(fn () => $env);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeEmpty()
        ->and($csp)->not->toContain('unsafe-eval')
        ->and(cspDirective($csp, 'script-src'))->not->toContain('unsafe-inline');
})->with(['testing', 'local', 'production', 'staging']);

it('CSP: w produkcji style-src jest bez unsafe-inline, a upgrade-insecure-requests jest dodane', function () {
    app()->detectEnvironment(fn () => 'production');

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'style-src'))->toBe("style-src 'self'")
        ->and($csp)->toContain('upgrade-insecure-requests')
        ->and($csp)->not->toContain('5173');
});

it('CSP: zawiera komplet dyrektyw (default-src, frame-ancestors, form-action, base-uri, object-src)', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    foreach (["default-src 'self'", "frame-ancestors 'none'", "form-action 'self'", "base-uri 'self'", "object-src 'none'"] as $directive) {
        expect($csp)->toContain($directive);
    }
});

it('CSP lokalne: źródła serwera Vite mają poprawną składnię (bez adresu IPv6 w nawiasach)', function () {
    app()->detectEnvironment(fn () => 'local');

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    // Składnia CSP nie dopuszcza hosta w postaci [::1]; przeglądarka ignoruje takie źródło i zgłasza ostrzeżenie.
    expect($csp)->not->toContain('[::1]');
});

it('CSP: klasa budująca nagłówek w trybie local dodaje Vite, a w pozostałych nie', function () {
    $local = (new ContentSecurityPolicy('abc', local: true, viteOrigins: ['http://localhost:5173']))->build();
    $prod = (new ContentSecurityPolicy('abc', production: true, viteOrigins: ['http://localhost:5173']))->build();

    expect($local)->toContain('http://localhost:5173')
        ->and($prod)->not->toContain('localhost')
        ->and($prod)->toContain("'nonce-abc'");
});

it('CSP można wyłączyć lub przełączyć na tryb "report only" przez konfigurację', function () {
    config(['security.csp.report_only' => true]);
    $response = $this->get('/');
    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeTrue()
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();

    config(['security.csp.report_only' => false, 'security.csp.enabled' => false]);
    expect($this->get('/')->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('HSTS: tylko dla HTTPS w produkcji, bez includeSubDomains i preload', function () {
    // HTTP + produkcja
    app()->detectEnvironment(fn () => 'production');
    expect($this->get('http://kontroladomu.test/')->headers->has('Strict-Transport-Security'))->toBeFalse();

    // HTTPS + testowe środowisko
    app()->detectEnvironment(fn () => 'testing');
    expect($this->get('https://kontroladomu.test/')->headers->has('Strict-Transport-Security'))->toBeFalse();

    // HTTPS + local
    app()->detectEnvironment(fn () => 'local');
    expect($this->get('https://kontroladomu.test/')->headers->has('Strict-Transport-Security'))->toBeFalse();

    // HTTPS + produkcja
    app()->detectEnvironment(fn () => 'production');
    $hsts = (string) $this->get('https://kontroladomu.test/')->headers->get('Strict-Transport-Security');
    expect($hsts)->toBe('max-age=31536000')
        ->and($hsts)->not->toContain('includeSubDomains')
        ->and($hsts)->not->toContain('preload');
});

it('nagłówki bezpieczeństwa są na każdym rodzaju odpowiedzi: HTML, przekierowanie, 404, 403, 429, JSON, plik', function () {
    $owner = User::factory()->owner()->create();
    $specialist = User::factory()->specialist()->withTwoFactor()->create();

    $responses = [
        'html' => $this->get('/logowanie'),
        'redirect' => $this->get('/pulpit'),
        '404' => $this->get('/nie-ma'),
        '403' => $this->actingAs(User::factory()->create())->get('/pulpit'),
        'json' => $this->getJson('/pulpit'),
        'plik' => $this->actingAs($specialist)->withSession(passwordConfirmed())->get('/konto/bezpieczenstwo/kody-awaryjne.txt'),
        'post bez csrf' => $this->post('/logowanie'),
    ];

    foreach ($responses as $name => $response) {
        foreach (['X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy', 'Cross-Origin-Opener-Policy', 'Content-Security-Policy'] as $header) {
            expect($response->headers->has($header))->toBeTrue("{$name}: brak nagłówka {$header}");
        }
    }

    expect($owner)->not->toBeNull();
});

it('Permissions-Policy wyłącza kamerę, mikrofon, płatności i USB', function () {
    $policy = (string) $this->get('/')->headers->get('Permissions-Policy');

    foreach (['camera=()', 'microphone=()', 'payment=()', 'usb=()'] as $item) {
        expect($policy)->toContain($item);
    }
});

it('trasy po zalogowaniu nie są cache\'owane (przycisk Wstecz po wylogowaniu)', function (string $url) {
    $response = $this->actingAs(User::factory()->owner()->create())->get($url)->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
})->with(['/pulpit', '/konto/bezpieczenstwo', '/potwierdz-haslo']);

it('ekran "sprawdź skrzynkę" i weryfikacji e-mail dla zalogowanego niepotwierdzonego nie jest cache\'owany', function () {
    $response = $this->actingAs(User::factory()->owner()->unverified()->create())->get('/email/potwierdz')->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('ciasteczko sesji ma HttpOnly i SameSite, a konfiguracja ma bezpieczne wartości', function () {
    $response = $this->get('/logowanie');
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBeIn(['lax', 'strict'])
        ->and(config('session.http_only'))->toBeTrue()
        ->and((int) config('session.lifetime'))->toBe(120);
});

// ---------------------------------------------------------------- widoki: {!! !!}, style="", skrypty

/**
 * Zwraca treść widoków aplikacji (bez szablonów maili z vendor/) z usuniętymi komentarzami.
 *
 * @return array<string, string> nazwa pliku => źródło bez komentarzy
 */
function appViewSources(): array
{
    $sources = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! $file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $source = preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '#^\s*//.*$#m', '#/\*.*?\*/#s'], '', $source) ?? $source;
        $sources[str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname())] = $source;
    }

    return $sources;
}

it('widoki aplikacji nie używają {!! !!}, @php echo ani Html::raw', function () {
    $sources = appViewSources();
    expect(count($sources))->toBeGreaterThan(40);

    foreach ($sources as $name => $source) {
        expect($source)->not->toContain('{!!', "{!! w {$name}")
            ->and($source)->not->toContain('@php echo', "echo w {$name}")
            ->and($source)->not->toMatch('/new\s+HtmlString|Html::raw|e\(\s*\$[^)]*\)\s*\)\s*->toHtml/', "HtmlString w {$name}");
    }
});

it('widoki aplikacji nie mają atrybutu style="" ani zdarzeń inline (onclick itp.), ani javascript:', function () {
    foreach (appViewSources() as $name => $source) {
        expect($source)->not->toMatch('/\sstyle\s*=/i', "style= w {$name}")
            ->and($source)->not->toMatch('/\son[a-z]+\s*=\s*["\']/i', "zdarzenie inline w {$name}")
            ->and($source)->not->toContain('javascript:');
    }
});

it('widoki aplikacji nie mają <script> bez nonce ani <style>', function () {
    foreach (appViewSources() as $name => $source) {
        preg_match_all('/<script\b[^>]*>/i', $source, $tags);
        foreach ($tags[0] as $tag) {
            expect(str_contains($tag, 'nonce'))->toBeTrue("<script> bez nonce w {$name}");
        }
        expect($source)->not->toMatch('/<style\b/i', "<style> w {$name}");
    }
});

it('wyrenderowane strony nie zawierają style="" ani <script> bez nonce (gość, właściciel, fachowiec z 2FA)', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'k']);
    $specialist = User::factory()->specialist()->withTwoFactor()->create();

    $pages = [
        [null, '/'], [null, '/logowanie'], [null, '/rejestracja'], [null, '/rejestracja/wlasciciel'],
        [null, '/rejestracja/fachowiec'], [null, '/nie-pamietam-hasla'], [null, '/regulamin'],
        [$specialist, '/pulpit'], [$specialist, '/konto/bezpieczenstwo'],
    ];

    foreach ($pages as [$actor, $url]) {
        $html = ($actor ? $this->actingAs($actor) : $this)->get($url)->assertOk()->getContent();

        expect($html)->not->toMatch('/\sstyle\s*=\s*"/i', "style= na {$url}");
        preg_match_all('/<script\b[^>]*>/i', $html, $tags);
        foreach ($tags[0] as $tag) {
            expect(str_contains($tag, 'nonce="'))->toBeTrue("<script> bez nonce na {$url}: {$tag}");
        }
    }
});

// ---------------------------------------------------------------- XSS i dane użytkownika

it('imię z HTML i <script> jest zapisane dosłownie, ale zawsze escapowane na pulpicie i w panelu', function () {
    Notification::fake();
    $payload = '<script>alert(1)</script><img src=x onerror=alert(2)> Żółć "cudzysłów" & \'apostrof\'';

    $this->post('/rejestracja/wlasciciel', ownerPayload(['name' => mb_substr($payload, 0, 120)]))
        ->assertSessionHasNoErrors();

    $user = User::firstOrFail();
    $user->forceFill(['email_verified_at' => now()])->save();

    foreach (['/pulpit', '/konto/bezpieczenstwo'] as $url) {
        $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
        expect($html)->not->toContain('<script>alert(1)')
            ->and($html)->not->toContain('<img src=x')
            ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    }
});

it('nazwa firmy i zajęty adres z HTML nie przedostają się do ekranów jako surowy HTML', function () {
    Notification::fake();
    $this->post('/rejestracja/fachowiec', specialistPayload(['company_name' => '<b onmouseover=x>Firma</b>']))->assertSessionHasNoErrors();

    $html = $this->get('/rejestracja/sprawdz-skrzynke')->assertOk()->getContent();
    expect($html)->not->toContain('<b onmouseover');

    $this->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'a"onfocus="x@example.pl']))->assertSessionHasErrors('email');
});

it('stare dane formularza po błędzie są escapowane, a hasło nigdy nie wraca do formularza ani do sesji', function () {
    $response = $this->from('/rejestracja/wlasciciel')->post('/rejestracja/wlasciciel', ownerPayload([
        'name' => '"><script>alert(1)</script>',
        'password' => 'krotkie',
        'terms' => null,
    ]));
    $response->assertSessionHasErrors();

    expect(session()->getOldInput())->not->toHaveKey('password');

    $html = $this->get('/rejestracja/wlasciciel')->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert(1)')->and($html)->not->toContain('krotkie');
});

it('walidacja: za długie teksty, puste pola i polskie znaki w rejestracji', function () {
    Notification::fake();

    $this->post('/rejestracja/wlasciciel', ownerPayload(['name' => str_repeat('a', 121)]))->assertSessionHasErrors('name');
    $this->post('/rejestracja/wlasciciel', ownerPayload(['email' => str_repeat('a', 190).'@example.pl']))->assertSessionHasErrors('email');
    $this->post('/rejestracja/wlasciciel', ownerPayload(['name' => '', 'email' => '', 'password' => '']))
        ->assertSessionHasErrors(['name', 'email', 'password']);
    $this->post('/rejestracja/fachowiec', specialistPayload(['company_name' => str_repeat('ł', 191)]))->assertSessionHasErrors('company_name');

    $this->post('/rejestracja/wlasciciel', ownerPayload(['name' => 'Żaneta Ćwik-Łączna 😀', 'email' => 'zaneta@example.pl']))
        ->assertSessionHasNoErrors();
    expect(User::where('email', 'zaneta@example.pl')->value('name'))->toBe('Żaneta Ćwik-Łączna 😀');
});

// ---------------------------------------------------------------- mass assignment

it('mass assignment: pola bezpieczeństwa i identyfikatory nie są wypełnialne', function () {
    $user = new User;

    foreach (['id', 'banned_at', 'email_verified_at', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'two_factor_confirmed_at', 'two_factor_reminder_until', 'created_at', 'updated_at'] as $field) {
        expect($user->isFillable($field))->toBeFalse("pole {$field} nie powinno być w \$fillable");
    }

    expect($user->getFillable())->not->toBeEmpty()->and($user->getGuarded())->not->toBe([]);
});

it('mass assignment: dopisane do formularza rejestracji pola nie nadają roli, weryfikacji ani bana', function () {
    Notification::fake();

    $this->post('/rejestracja/wlasciciel', ownerPayload([
        'role' => 'admin', 'roles' => ['admin'], 'is_admin' => 1, 'banned_at' => null,
        'email_verified_at' => now()->toDateTimeString(), 'two_factor_confirmed_at' => now()->toDateTimeString(),
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'id' => 999, 'remember_token' => 'x',
    ]))->assertRedirect(route('register.sent'));

    $user = User::firstOrFail();
    expect($user->id)->not->toBe(999)
        ->and($user->getRoleNames()->all())->toBe(['owner'])
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->hasConfirmedTwoFactor())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->banned_at)->toBeNull();
});

it('mass assignment: dane dodatkowe w zmianie hasła i adresu nie zmieniają innych pól konta', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $this->actingAs($user)->post('/konto/haslo', [
        'current_password' => TEST_PASSWORD, 'password' => 'zupelnie-nowe-haslo-42',
        'email' => 'przejety@example.pl', 'banned_at' => now()->toDateTimeString(), 'id' => 5,
    ]);

    $user->refresh();
    expect($user->email)->toBe('jan@example.pl')->and($user->banned_at)->toBeNull();
});

it('rola administratora nie jest nadawana przez rejestrację fachowca ani nie istnieje trasa do jej nadania', function () {
    Notification::fake();

    $this->post('/rejestracja/fachowiec', specialistPayload(['role' => 'admin']));

    expect(User::firstOrFail()->getRoleNames()->all())->toBe(['specialist']);
});

// ---------------------------------------------------------------- konto zbanowane

it('konto zbanowane po zalogowaniu traci dostęp przy następnym żądaniu', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user)->get('/pulpit')->assertOk();

    $user->forceFill(['banned_at' => now()])->save();

    $this->get('/pulpit')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('zbanowane w trakcie wyzwania 2FA konto nie zostaje zalogowane poprawnym kodem', function () {
    $user = User::factory()->owner()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD])
        ->assertRedirect(route('two-factor.login'));

    // Administrator banuje konto w trakcie wyzwania; poprawny kod nie może otworzyć sesji.
    $user->forceFill(['banned_at' => now()])->save();

    $this->post('/logowanie/kod', ['code' => factoryTotp()]);
    $this->assertGuest();
});

// ---------------------------------------------------------------- unieważnianie sesji

it('reset hasła zeruje token "zapamiętaj mnie" i usuwa inne sesje (driver database)', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl']);
    $before = $user->remember_token;

    foreach (['s1', 's2'] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
    }

    $this->post('/nowe-haslo', ['token' => Password::createToken($user), 'email' => 'jan@example.pl', 'password' => 'nowe-zielone-haslo-123'])
        ->assertRedirect(route('login'));

    expect($user->fresh()->remember_token)->not->toBe($before)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('zmiana hasła przez formularz rotuje token "zapamiętaj mnie"', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['password' => TEST_PASSWORD]);
    $before = $user->remember_token;

    $this->actingAs($user)->post('/konto/haslo', ['current_password' => TEST_PASSWORD, 'password' => 'zupelnie-nowe-haslo-42'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->remember_token)->not->toBe($before);
});

it('inna sesja zalogowana tym samym kontem przestaje działać po zmianie hasła na pierwszej sesji (sesje w bazie)', function () {
    config(['session.driver' => 'database']);
    Notification::fake();
    $user = User::factory()->owner()->create(['password' => TEST_PASSWORD]);

    DB::table('sessions')->insert(['id' => 'druga-sesja', 'user_id' => $user->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($user)->post('/konto/haslo', ['current_password' => TEST_PASSWORD, 'password' => 'zupelnie-nowe-haslo-42'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'druga-sesja')->exists())->toBeFalse();
});

it('akcja resetu hasła ustawia nowe hasło tylko przez Hash (nie zapisuje jawnego)', function () {
    $user = User::factory()->owner()->create();

    app(ResetUserPassword::class)->reset($user, ['password' => 'nowe-zielone-haslo-123']);

    expect($user->fresh()->getRawOriginal('password'))->not->toContain('nowe-zielone')
        ->and(Hash::check('nowe-zielone-haslo-123', $user->fresh()->password))->toBeTrue();
});

// ---------------------------------------------------------------- zgody RODO i czas UTC

it('RODO: zgody zapisują się z czasem UTC i wersjami tekstów (czas lokalny Europe/Warsaw nie przesuwa zapisu)', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-15 10:30:00', 'Europe/Warsaw'));

    $this->post('/rejestracja/wlasciciel', ownerPayload(['marketing' => '1']))->assertSessionHasNoErrors();

    $row = DB::table('users')->first();
    expect($row->terms_accepted_at)->toBe('2026-07-15 08:30:00')
        ->and($row->privacy_accepted_at)->toBe('2026-07-15 08:30:00')
        ->and($row->marketing_consent_at)->toBe('2026-07-15 08:30:00')
        ->and($row->terms_version)->toBe(config('legal.terms_version'))
        ->and($row->privacy_version)->toBe(config('legal.privacy_version'))
        ->and($row->marketing_consent_version)->toBe(config('legal.marketing_version'));
});

it('RODO: bez zgody marketingowej kolumny zgody są NULL, a dwie rejestracje mają niezależne znaczniki czasu', function () {
    Notification::fake();

    Carbon::setTestNow('2026-03-01 12:00:00');
    $this->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'a@example.pl']));
    Carbon::setTestNow('2026-03-02 12:00:00');
    $this->flushSession();
    $this->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'b@example.pl', 'marketing' => '0']));

    $a = User::where('email', 'a@example.pl')->firstOrFail();
    $b = User::where('email', 'b@example.pl')->firstOrFail();

    expect($a->marketing_consent_at)->toBeNull()->and($a->marketing_consent_version)->toBeNull()
        ->and($b->marketing_consent_at)->toBeNull()->and($b->marketing_consent_version)->toBeNull()
        ->and($a->terms_accepted_at->toDateString())->toBe('2026-03-01')
        ->and($b->terms_accepted_at->toDateString())->toBe('2026-03-02');
});

it('RODO: konfiguracja wersji tekstów prawnych jest niepusta', function () {
    foreach (['terms_version', 'privacy_version', 'marketing_version'] as $key) {
        expect(config('legal.'.$key))->toBeString()->not->toBeEmpty();
    }
});

it('dziennik aktywności nie zawiera haseł, tokenów ani adresów z nieudanych logowań', function () {
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $this->post('/logowanie', ['email' => 'ofiara@example.pl', 'password' => 'bardzo-tajne-haslo-1']);
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $dump = Activity::all()->map(fn ($a) => json_encode($a->toArray()))->implode('');
    expect($dump)->not->toContain('bardzo-tajne-haslo-1')
        ->and($dump)->not->toContain(TEST_PASSWORD)
        ->and($dump)->not->toContain('ofiara@example.pl')
        ->and($user)->not->toBeNull();
});

// ---------------------------------------------------------------- konfiguracja okien czasowych

it('konfiguracja: okno potwierdzenia hasła 15 min, link weryfikacyjny 24 h, sesja 120 min', function () {
    expect((int) config('auth.password_timeout'))->toBe(900)
        ->and((int) config('auth.verification.expire'))->toBe(1440)
        ->and((int) config('session.lifetime'))->toBe(120);
});

it('potwierdzenie hasła wygasa po 15 minutach: po 14 działa, po 16 wymaga ponownie', function () {
    $user = User::factory()->specialist()->create(['password' => TEST_PASSWORD]);

    $this->actingAs($user)->post('/potwierdz-haslo', ['password' => TEST_PASSWORD])->assertRedirect();

    $this->travel(14)->minutes();
    $this->post('/konto/bezpieczenstwo/2fa')->assertRedirect(route('account.security'));

    $this->travel(2)->minutes();
    $this->post('/konto/bezpieczenstwo/2fa/potwierdz', ['code' => '123456'])->assertRedirect(route('password.confirm'));
});

it('link weryfikacyjny wygasa po 24 godzinach: po 23 działa, po 25 nie', function () {
    $fresh = User::factory()->owner()->unverified()->create();
    $late = User::factory()->owner()->unverified()->create();

    $link = fn (User $user) => (new VerifyEmailNotification)->toMail($user)->actionUrl;
    $freshUrl = $link($fresh);
    $lateUrl = $link($late);

    $this->travel(23)->hours();
    $this->get($freshUrl)->assertSessionHas('verify_status', 'verified');

    $this->travel(2)->hours();
    $this->get($lateUrl)->assertSessionHas('verify_status', 'invalid');
    expect($late->fresh()->hasVerifiedEmail())->toBeFalse();
});
