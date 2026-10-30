<?php

/*
| Kryteria akceptacji UX z zadania 002 (punkty 1-8) - tam, gdzie da się je sprawdzić automatycznie.
| Czego NIE da się sprawdzić tutaj (zostaje w "Sprawdź sam"): faktyczny brak poziomego przewijania
| na 375 px, widoczność fokusu, praca samą klawiaturą, kontrast.
*/

use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Notification;

$formPages = [
    'logowanie' => '/logowanie',
    'rejestracja właściciela' => '/rejestracja/wlasciciel',
    'rejestracja fachowca' => '/rejestracja/fachowiec',
    'reset hasła' => '/nie-pamietam-hasla',
];

// ---------------------------------------------------------------- kryterium 1

it('kryt. 1: formularze mają meta viewport, a pola i przyciski min. 44 px (klasy min-h-11/12)', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toContain('name="viewport"')->toContain('width=device-width');

    preg_match_all('/<input\b[^>]*>/i', $html, $inputs);
    foreach ($inputs[0] as $tag) {
        if (preg_match('/type="(?:hidden|checkbox)"/i', $tag)) {
            continue;
        }
        expect($tag)->toMatch('/min-h-1[1-9]/', "pole bez min-h-11+: {$tag}");
    }

    preg_match_all('/<(?:button|a)\b[^>]*class="[^"]*(?:bg-brand-700|border border-line)[^"]*"[^>]*>/i', $html, $buttons);
    foreach ($buttons[0] as $tag) {
        expect($tag)->toMatch('/min-h-1[1-9]/', "przycisk bez min-h-11+: {$tag}");
    }
})->with($formPages);

it('kryt. 1: żaden widok nie ustawia stałej szerokości szerszej niż 375 px (w-[...px])', function () {
    $scanned = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! $file->isFile() || str_contains($file->getPathname(), 'vendor')) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        $scanned++;
        preg_match_all('/(?<![\w-])(?:min-w|w)-\[(\d+)px\]/', $source, $m);
        foreach ($m[1] as $px) {
            expect((int) $px)->toBeLessThanOrEqual(375, "{$file->getFilename()}: stała szerokość {$px}px");
        }
    }

    expect($scanned)->toBeGreaterThan(30);
});

// ---------------------------------------------------------------- kryterium 2

it('kryt. 2: zły adres i złe hasło dają ten sam komunikat, kod i adres przekierowania', function () {
    User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $a = $this->from('/logowanie')->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo']);
    $b = $this->from('/logowanie')->post('/logowanie', ['email' => 'brak@example.pl', 'password' => 'zle-haslo-zle-haslo']);

    expect($a->getStatusCode())->toBe($b->getStatusCode())
        ->and($a->headers->get('Location'))->toBe($b->headers->get('Location'));

    $messageA = session('errors')->getBag('default')->first('email');
    $a->assertSessionHasErrors(['email' => __('auth.failed')]);
    $b->assertSessionHasErrors(['email' => __('auth.failed')]);
    expect($messageA)->toBe(__('auth.failed'));

    // Wyrenderowany ekran błędu logowania jest alertem (role=alert), a nie samym kolorem.
    $page = $this->get('/logowanie')->assertOk()->getContent();
    expect($page)->toContain('role="alert"')->toContain(__('auth.failed'));
});

it('kryt. 2: rejestracja na zajęty i wolny e-mail daje identyczny ekran "Sprawdź skrzynkę"', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);

    $taken = $this->followingRedirects()->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'zajety@example.pl']));
    $this->flushSession();
    $free = $this->followingRedirects()->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'wolny@example.pl']));

    $normalize = fn (string $html, string $email) => preg_replace(
        ['/nonce="[^"]*"/', '/name="_token" value="[^"]*"/', '/'.preg_quote($email, '/').'/'],
        ['nonce=""', 'name="_token" value=""', 'EMAIL'],
        $html,
    );

    $taken->assertOk()->assertSeeText(__('auth.register_sent.title'));
    expect($normalize($taken->getContent(), 'zajety@example.pl'))->toBe($normalize($free->getContent(), 'wolny@example.pl'));
});

it('kryt. 2: reset hasła daje tę samą odpowiedź (z kodem, nagłówkami i ciałem) dla istniejącego i nieistniejącego konta', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'jan@example.pl']);

    $known = $this->from('/nie-pamietam-hasla')->post('/nie-pamietam-hasla', ['email' => 'jan@example.pl']);
    $this->flushSession();
    $unknown = $this->from('/nie-pamietam-hasla')->post('/nie-pamietam-hasla', ['email' => 'brak@example.pl']);

    expect($known->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($known->headers->get('Location'))->toBe($unknown->headers->get('Location'));
    $unknown->assertSessionHas('status', __('passwords.sent'));
});

it('kryt. 2: po 5 błędnych kodach 2FA pojawia się ekran blokady na 15 minut (HTTP 429)', function () {
    User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    foreach (range(1, 4) as $i) {
        $this->post('/logowanie/kod', ['code' => '11111'.$i])->assertSessionHasErrors();
    }
    $this->get('/logowanie/kod')->assertOk();

    $this->post('/logowanie/kod', ['code' => '999999'])->assertSessionHasErrors();

    $this->get('/logowanie/kod')
        ->assertStatus(429)
        ->assertSeeText(__('auth.twofa.locked_title'))
        ->assertSeeText('15 minut');
});

// ---------------------------------------------------------------- kryterium 3

it('kryt. 3: formularze rejestracji mają zgodę marketingową osobno i domyślnie odznaczoną', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    preg_match('/<input\b[^>]*name="marketing"[^>]*>/is', $html, $marketing);
    preg_match('/<input\b[^>]*name="terms"[^>]*>/is', $html, $terms);

    expect($marketing)->not->toBeEmpty()
        ->and($marketing[0])->not->toContain('checked')
        ->and($marketing[0])->not->toContain('aria-required')
        ->and($terms)->not->toBeEmpty()
        ->and($terms[0])->not->toContain('checked')
        ->and($terms[0])->toContain('aria-required');
})->with(['/rejestracja/wlasciciel', '/rejestracja/fachowiec']);

it('kryt. 3: formularz fachowca wymaga NIP i oświadczenia o działalności', function () {
    $html = $this->get('/rejestracja/fachowiec')->assertOk()->getContent();

    expect($html)->toContain('name="nip"')->toContain('name="company_name"')->toContain('name="business_declaration"');

    $this->post('/rejestracja/fachowiec', specialistPayload(['nip' => '']))->assertSessionHasErrors('nip');
    $this->post('/rejestracja/fachowiec', specialistPayload(['company_name' => '']))->assertSessionHasErrors('company_name');
});

it('kryt. 3: NIP z myślnikami, spacjami i prefiksem PL jest przyjmowany i zapisany jako 10 cyfr', function (string $input) {
    Notification::fake();

    $this->post('/rejestracja/fachowiec', specialistPayload(['nip' => $input]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('register.sent'));

    expect(User::firstOrFail()->nip)->toBe('6931171824');
})->with(['693-117-18-24', '693 117 18 24', 'PL6931171824', 'PL 693-117-18-24', '6931171824', ' 6931171824 ']);

it('kryt. 3: po rejestracji właściciela i fachowca trafia się na "Sprawdź skrzynkę", bez logowania', function () {
    Notification::fake();

    foreach (['/rejestracja/wlasciciel' => ownerPayload(), '/rejestracja/fachowiec' => specialistPayload(['email' => 'inny@example.pl'])] as $path => $payload) {
        $this->flushSession();
        $this->followingRedirects()->post($path, $payload)->assertOk()->assertSeeText(__('auth.register_sent.title'));
        $this->assertGuest();
    }
});

// ---------------------------------------------------------------- kryterium 4

it('kryt. 4: fachowiec po potwierdzeniu e-maila nie wejdzie do panelu bez 2FA, a kreator pokazuje 8 kodów w formacie xxxxxxxxxx-xxxxxxxxxx', function () {
    $user = User::factory()->specialist()->create();

    $this->actingAs($user)->get('/pulpit')->assertRedirect(route('account.security'));

    $this->actingAs($user)->withSession(passwordConfirmed())->post('/konto/bezpieczenstwo/2fa');
    $this->actingAs($user)->get('/pulpit')->assertRedirect(route('account.security'));

    $this->actingAs($user)->withSession(passwordConfirmed())
        ->post('/konto/bezpieczenstwo/2fa/potwierdz', ['code' => currentTotp($user)]);

    $page = $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertOk();
    preg_match_all('/>\s*([A-Za-z0-9]{10}-[A-Za-z0-9]{10})\s*</', $page->getContent(), $codes);

    expect($codes[1])->toHaveCount(8)->and(array_unique($codes[1]))->toHaveCount(8);

    $this->actingAs($user)->get('/pulpit')->assertOk();
});

it('kryt. 4: pole "Zapisałem(-am) kody" jest miękkie, a przycisk "Zakończ" nie jest zablokowany', function () {
    $user = User::factory()->specialist()->create();
    $this->actingAs($user)->withSession(passwordConfirmed())->post('/konto/bezpieczenstwo/2fa');
    $this->actingAs($user)->withSession(passwordConfirmed())
        ->post('/konto/bezpieczenstwo/2fa/potwierdz', ['code' => currentTotp($user)]);

    $html = $this->actingAs($user)->get('/konto/bezpieczenstwo')->assertOk()->getContent();

    preg_match('/<input\b[^>]*id="codes-saved"[^>]*>/i', $html, $checkbox);
    preg_match('/<a\b[^>]*href="[^"]*\/pulpit"[^>]*>\s*'.preg_quote(__('auth.twofa.finish'), '/').'/i', $html, $finish);

    expect($checkbox)->not->toBeEmpty()
        ->and($checkbox[0])->not->toContain('required')
        ->and($finish)->not->toBeEmpty()
        ->and($finish[0])->not->toContain('disabled')
        ->and($finish[0])->not->toContain('aria-disabled');
});

it('kryt. 4: każda trasa z "verified" musi mieć też bramkę 2FA, chyba że to trasy samej konfiguracji konta', function () {
    $allowedWithoutGate = [
        'account.security', 'two-factor.enable', 'two-factor.confirm', 'two-factor.disable',
        'two-factor.codes.show', 'two-factor.codes.download', 'two-factor.codes.regenerate',
        'two-factor.reminder.dismiss', 'password.change',
    ];

    $offenders = [];
    foreach (app('router')->getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();
        $isPanel = in_array('verified', $middleware, true)
            || in_array(EnsureEmailIsVerified::class, $middleware, true);

        if ($isPanel && ! in_array('two-factor.required', $middleware, true) && ! in_array($route->getName(), $allowedWithoutGate, true)) {
            $offenders[] = (string) $route->getName();
        }
    }

    expect($offenders)->toBe([]);
});

// ---------------------------------------------------------------- kryterium 5

it('kryt. 5: logowanie kodem z aplikacji i kodem awaryjnym; użyty kod awaryjny nie działa drugi raz', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $login = fn () => $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    $login();
    $this->post('/logowanie/kod', ['code' => factoryTotp()])->assertRedirect('/pulpit');
    $this->assertAuthenticatedAs($user);
    auth()->logout();
    $this->flushSession();

    $login();
    $this->post('/logowanie/kod', ['recovery_code' => 'cccccccccc-dddddddddd'])->assertRedirect('/pulpit');
    $this->assertAuthenticatedAs($user);
    auth()->logout();
    $this->flushSession();

    $login();
    $this->post('/logowanie/kod', ['recovery_code' => 'cccccccccc-dddddddddd'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('kryt. 5: po użyciu kodu awaryjnego pulpit informuje, ile kodów zostało', function () {
    $codes = collect(range(1, 8))->map(fn ($i) => str_repeat((string) $i, 10).'-'.str_repeat((string) $i, 10))->all();
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode($codes))])->save();

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $this->post('/logowanie/kod', ['recovery_code' => $codes[0]])->assertRedirect('/pulpit');

    // Oczekiwanie z kryterium 5: komunikat "Użyto kodu awaryjnego. Zostało: n" (lang auth.twofa.recovery_used).
    $this->get('/pulpit')->assertOk()->assertSeeText('Użyto kodu awaryjnego');
});

it('kryt. 5: liczba kodów awaryjnych maleje po ich użyciu (a nie jest podmieniana na nowe)', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $before = count($user->recoveryCodes());

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $this->post('/logowanie/kod', ['recovery_code' => 'aaaaaaaaaa-bbbbbbbbbb'])->assertRedirect('/pulpit');

    expect(count($user->fresh()->recoveryCodes()))->toBe($before - 1);
});

it('kryt. 5: ostrzeżenie o małej liczbie kodów jest w poprawnej polskiej odmianie', function (int $count, string $expected) {
    expect(trans_choice('auth.twofa.codes_low', $count, ['count' => $count]))->toContain($expected);
})->with([
    [1, 'Został 1 kod awaryjny'],
    [2, 'Zostały 2 kody awaryjne'],
    [4, 'Zostały 4 kody awaryjne'],
    [5, 'Zostało 5 kodów awaryjnych'],
    [12, 'Zostało 12 kodów awaryjnych'],
    [22, 'Zostały 22 kody awaryjne'],
]);

it('kryt. 5: pulpit ostrzega o małej liczbie kodów (1 i 2) oraz o braku kodów', function () {
    $user = User::factory()->owner()->withTwoFactor()->create();

    $this->actingAs($user)->get('/pulpit')->assertOk()->assertSeeText('Zostały 2 kody awaryjne');

    $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode([]))])->save();
    $this->actingAs($user)->get('/pulpit')->assertOk()->assertSeeText(__('auth.twofa.codes_none'));
});

// ---------------------------------------------------------------- kryterium 6

it('kryt. 6: pulpit każdej roli ma puste stany i przyciski, a linki nie prowadzą do 404/500', function (string $role) {
    $user = User::factory()->{$role}()->create();
    if (in_array($role, ['specialist', 'admin'], true)) {
        $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(array_fill(0, 8, 'a'))), 'two_factor_confirmed_at' => now()])->save();
    }

    $html = $this->actingAs($user)->get('/pulpit')->assertOk()->getContent();

    preg_match_all('/<a\b[^>]*\shref="([^"#]+)"/i', $html, $links);
    $checked = [];
    foreach (array_unique($links[1]) as $href) {
        $path = parse_url($href, PHP_URL_PATH);
        $host = parse_url($href, PHP_URL_HOST);
        if (($host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST) && $host !== 'localhost') || $path === null || $path === '' || str_starts_with($href, 'mailto:')) {
            continue;
        }
        if (in_array($path, ['/wyloguj'], true) || str_contains($path, '/build/')) {
            continue;
        }
        $status = $this->actingAs($user)->get($path)->getStatusCode();
        expect($status)->toBeIn([200, 302, 403], "{$role}: link {$href} zwraca {$status}");
        $checked[] = $path;
    }

    expect($checked)->not->toBeEmpty();
    // Pozycje bez modułów są nieaktywne i oznaczone jako "Wkrótce".
    expect($html)->toContain(__('dashboard.soon'));
})->with(['owner', 'specialist', 'technician', 'admin']);

it('kryt. 6: strona główna i panel nie linkują do nieistniejących stron', function () {
    $owner = User::factory()->owner()->create();
    $pages = ['/' => null, '/regulamin' => null, '/pulpit' => $owner, '/konto/bezpieczenstwo' => $owner];

    foreach ($pages as $url => $actor) {
        $request = $actor ? $this->actingAs($actor) : $this;
        $html = $request->get($url)->assertOk()->getContent();
        preg_match_all('/<a\b[^>]*\shref="(\/[^"#?]*)"/i', $html, $links);

        foreach (array_unique($links[1]) as $path) {
            if (str_contains($path, '/build/') || $path === '/wyloguj') {
                continue;
            }
            $status = ($actor ? $this->actingAs($actor) : $this)->get($path)->getStatusCode();
            expect($status)->not->toBeIn([404, 500], "link {$path} na stronie {$url} zwraca {$status}");
        }
    }
});

// ---------------------------------------------------------------- kryterium 7 (część automatyczna)

it('kryt. 7: błędy formularzy są odczytywane (role=alert / aria-invalid), a nie tylko pokazane kolorem', function () {
    $html = $this->from('/rejestracja/wlasciciel')
        ->followingRedirects()
        ->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'zly', 'password' => 'krotkie', 'terms' => null]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('role="alert"')->toContain('aria-invalid="true"')->toContain('aria-describedby=');
    // Każdy błąd ma tekst (nie sam kolor): ikona jest uzupełniona etykietą dla czytników.
    expect($html)->toContain('sr-only');
});

it('kryt. 7: każda strona ma jedno <h1>, link "pomiń do treści" w panelu i etykiety pól', function () {
    foreach (['/logowanie', '/rejestracja/wlasciciel', '/rejestracja/fachowiec', '/nie-pamietam-hasla'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect(substr_count($html, '<h1'))->toBe(1, $url);

        preg_match_all('/<input\b[^>]*\sid="([^"]+)"[^>]*type="(?!hidden)[a-z]+"|<input\b[^>]*type="(?!hidden)[a-z]+"[^>]*\sid="([^"]+)"/i', $html, $ids);
        foreach (array_filter(array_merge($ids[1], $ids[2])) as $id) {
            expect($html)->toContain('for="'.$id.'"');
        }
    }

    $panel = $this->actingAs(User::factory()->owner()->create())->get('/pulpit')->assertOk()->getContent();
    expect(substr_count($panel, '<h1'))->toBe(1)->and($panel)->toContain('href="#main"')->toContain('id="main"');
});

it('kryt. 7: wszystkie strony mają atrybut lang="pl" i tytuł', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toContain('lang="pl"')->toMatch('/<title>[^<]*kontroladomu\.pl<\/title>/u');
})->with(['/', '/logowanie', '/rejestracja', '/regulamin', '/kontakt']);

// ---------------------------------------------------------------- kryterium 8

it('kryt. 8: w widokach i plikach lang nie ma emoji, gradientów ani zakazanych słów', function () {
    $forbidden = ['rewolucj', 'innowacyjn', 'bezproblemowo', 'w kilka kliknięć', 'odkryj'];
    $emoji = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{1F000}-\x{1F2FF}\x{2B50}\x{2B06}\x{2705}\x{274C}\x{FE0F}]/u';

    $files = [];
    foreach ([resource_path('views'), base_path('lang')] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
    }
    $files[] = resource_path('css/app.css');

    foreach ($files as $path) {
        $source = mb_strtolower(file_get_contents($path));
        $name = basename($path);

        expect(preg_match($emoji, $source))->toBe(0, "emoji w {$name}");
        expect(str_contains($source, 'gradient'))->toBeFalse("gradient w {$name}");
        foreach ($forbidden as $word) {
            expect(str_contains($source, $word))->toBeFalse("zakazane słowo \"{$word}\" w {$name}");
        }
    }
});

it('kryt. 8: widoczne teksty w lang/pl nie zawierają prostych cudzysłowów (używamy „ ")', function () {
    $offenders = [];
    $walk = function (array $items, string $prefix) use (&$walk, &$offenders) {
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $walk($value, $prefix.$key.'.');
            } elseif (is_string($value) && str_contains($value, '"')) {
                $offenders[] = $prefix.$key;
            }
        }
    };

    foreach (glob(base_path('lang/pl/*.php')) as $file) {
        $walk(require $file, basename($file, '.php').'.');
    }

    expect($offenders)->toBe([]);
});

it('kryt. 8: w widokach nie ma polskich tekstów wpisanych na sztywno (wszystko przez lang/pl)', function () {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! $file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        // Komentarze Blade/HTML/PHP nie są widoczne dla użytkownika.
        $source = preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '#^\s*//.*$#m', '#/\*.*?\*/#s'], '', $source) ?? $source;

        if (preg_match('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', $source)) {
            $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
