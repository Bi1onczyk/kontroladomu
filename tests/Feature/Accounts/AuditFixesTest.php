<?php

/*
| Poprawki po audycie bezpieczeństwa zadania 002 (S1-S6, N2, N5, usunięcie axios).
| Każdy test opisuje atak, który przed poprawką się udawał.
*/

use App\Domain\Accounts\Notifications\PasswordChangedNotification;
use App\Domain\Accounts\Notifications\RegisterAttemptNotification;
use App\Domain\Accounts\Notifications\ResetPasswordNotification;
use App\Domain\Accounts\Support\LoginFailureCounter;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Security\ProductionConfigGuard;
use App\Support\Security\TrustedProxyConfig;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

const AUDIT_PASSWORD = 'zielony-dach-w-lutym';

/** Podpisany link potwierdzający NOWY adres (jak w VerifyPendingEmailNotification). */
function pendingLink(User $user, string $email, int $minutes = 1440): string
{
    return URL::temporarySignedRoute('email.change.verify', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => sha1($email),
    ]);
}

/** Ustawia konfigurację uznawaną za poprawną na produkcji. */
function validProductionConfig(): void
{
    config([
        'app.debug' => false,
        'app.url' => 'https://kontroladomu.pl',
        'security.trusted_proxies' => '173.245.48.0/20,103.21.244.0/22',
        'services.turnstile.enabled' => true,
        'services.turnstile.site_key' => '0x4AAAAAAAprodukcyjny',
        'services.turnstile.secret' => '0x4AAAAAAAsekretprodukcyjny',
        'queue.default' => 'database',
        'mail.default' => 'smtp',
        'session.secure' => true,
        'auth.check_pwned' => true,
        'backup.backup.password' => 'haslo-archiwum-testowe-20+',
    ]);
}

// ---------------------------------------------------------------- S1: zmiana e-maila bez enumeracji

it('S1: zmiana adresu na zajęty nie ujawnia konta (ta sama odpowiedź), nic nie zmienia i powiadamia właściciela', function () {
    Notification::fake();
    $holder = User::factory()->owner()->create(['email' => 'zajety@example.pl']);
    $user = User::factory()->owner()->unverified()->create(['email' => 'moj@example.pl']);

    $taken = $this->actingAs($user)->post('/email/zmien', ['email' => 'zajety@example.pl']);
    $takenStatus = session('status');
    $user->refresh();
    expect($user->email)->toBe('moj@example.pl');

    $this->flushSession();
    $free = $this->actingAs($user)->post('/email/zmien', ['email' => 'wolny@example.pl']);

    // Napastnik nie widzi różnicy: ten sam kod, ten sam cel, brak błędów walidacji.
    expect($taken->getStatusCode())->toBe($free->getStatusCode());
    $taken->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
    $free->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
    expect($takenStatus)->toBe(__('auth.email_change.success', ['email' => 'zajety@example.pl']));

    Notification::assertSentTo($holder, RegisterAttemptNotification::class);
});

it('S1: ekran potwierdzenia pokazuje wpisany adres także wtedy, gdy był zajęty', function () {
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);
    $user = User::factory()->owner()->unverified()->create(['email' => 'moj@example.pl']);

    $this->actingAs($user)->post('/email/zmien', ['email' => 'zajety@example.pl']);

    $this->actingAs($user)->get('/email/potwierdz')->assertOk()->assertViewHas('displayEmail', 'zajety@example.pl');
});

it('S1: wiadomość o próbie użycia adresu idzie najwyżej raz na godzinę', function () {
    Notification::fake();
    $holder = User::factory()->owner()->create(['email' => 'zajety@example.pl']);
    $user = User::factory()->owner()->unverified()->create();

    $this->actingAs($user)->post('/email/zmien', ['email' => 'zajety@example.pl']);
    $this->actingAs($user)->post('/email/zmien', ['email' => 'zajety@example.pl']);

    Notification::assertSentToTimes($holder, RegisterAttemptNotification::class, 1);
});

it('S1: wyścig przy podmianie adresu po kliknięciu (naruszenie unikalności) daje ekran "link nieważny", nie 500', function () {
    $user = User::factory()->owner()->unverified()->create(['email' => 'moj@example.pl', 'pending_email' => 'wolny@example.pl']);

    User::updating(function () {
        throw new UniqueConstraintViolationException('sqlite', 'update users', [], new Exception('duplicate'));
    });

    $this->get(pendingLink($user, 'wolny@example.pl'))
        ->assertRedirect(route('verification.done'))
        ->assertSessionHas('verify_status', 'invalid');

    User::flushEventListeners();
});

// ---------------------------------------------------------------- S2: brak blokowania cudzego resetu / rejestracji

function turnstileFake(): void
{
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'sekret']);
    Http::fake(fn ($request) => Http::response(['success' => $request['response'] === 'dobry'], 200));
}

it('S2: napastnik nie zablokuje cudzego resetu hasła żądaniami z adresem ofiary', function () {
    Notification::fake();
    turnstileFake();
    $victim = User::factory()->owner()->create(['email' => 'ofiara@example.pl']);

    // Atak z jednego IP: żądania bez poprawnego Turnstile (przed poprawką liczyły się do limitu 3/h na adres).
    $attacker = $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9']);
    foreach (range(1, 4) as $i) {
        $attacker->post('/nie-pamietam-hasla', ['email' => 'ofiara@example.pl', 'cf-turnstile-response' => 'zly'])
            ->assertSessionHasErrors('cf-turnstile-response');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])
        ->post('/nie-pamietam-hasla', ['email' => 'ofiara@example.pl', 'cf-turnstile-response' => 'dobry'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($victim, ResetPasswordNotification::class);
});

it('S2: nawet z poprawnym Turnstile z wielu IP napastnik nie wymusi więcej niż 3 maili resetu na godzinę, a odpowiedź zostaje neutralna', function () {
    Notification::fake();
    $victim = User::factory()->owner()->create(['email' => 'ofiara@example.pl']);

    foreach (range(1, 6) as $i) {
        $this->travel(2)->minutes(); // omija 60-sekundowy odstęp brokera haseł
        $this->withServerVariables(['REMOTE_ADDR' => "10.2.2.{$i}"])
            ->post('/nie-pamietam-hasla', ['email' => 'ofiara@example.pl'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    Notification::assertSentToTimes($victim, ResetPasswordNotification::class, 3);
});

it('S2: napastnik nie zablokuje cudzej rejestracji żądaniami z adresem ofiary', function () {
    Notification::fake();
    turnstileFake();

    $attacker = $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9']);
    foreach (range(1, 4) as $i) {
        $attacker->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'ofiara@example.pl', 'cf-turnstile-response' => 'zly']))
            ->assertSessionHasErrors('cf-turnstile-response');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])
        ->post('/rejestracja/wlasciciel', ownerPayload(['email' => 'ofiara@example.pl', 'cf-turnstile-response' => 'dobry']))
        ->assertRedirect(route('register.sent'));

    expect(User::where('email', 'ofiara@example.pl')->exists())->toBeTrue();
});

// ---------------------------------------------------------------- S3: limit na konto, zaufane proxy

it('S3: po 5 nieudanych logowaniach na jedno konto z RÓŻNYCH adresów IP kolejne wymaga Turnstile', function () {
    turnstileFake();
    User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => AUDIT_PASSWORD]);

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.3.3.{$i}"])
            ->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    $fresh = $this->withServerVariables(['REMOTE_ADDR' => '10.3.3.99']);
    $fresh->post('/logowanie', ['email' => 'jan@example.pl', 'password' => AUDIT_PASSWORD])
        ->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();

    // Inne konto z tego samego świeżego IP nie jest dotknięte.
    User::factory()->owner()->create(['email' => 'inne@example.pl', 'password' => AUDIT_PASSWORD]);
    $fresh->post('/logowanie', ['email' => 'inne@example.pl', 'password' => AUDIT_PASSWORD])->assertRedirect('/pulpit');
});

it('S3: ekran logowania wie, że dla tego konta trzeba pokazać Turnstile', function () {
    $counter = app(LoginFailureCounter::class);
    foreach (range(1, 5) as $i) {
        $counter->hit("10.4.4.{$i}", 'jan@example.pl');
    }

    $this->withSession(['_old_input' => ['email' => 'jan@example.pl']])->get('/logowanie')
        ->assertViewHas('requiresTurnstile', true);
    $this->flushSession();
    $this->get('/logowanie')->assertViewHas('requiresTurnstile', false);
});

it('S3: licznik konta nie zawiera jawnego adresu e-mail w cache', function () {
    app(LoginFailureCounter::class)->hit('10.5.5.5', 'Jan@Example.pl');

    expect(Cache::get('login-failures-account:'.sha1('jan@example.pl')))->toBe(1);
});

it('S3: zaufane proxy podaje się zakresami, a nagłówek X-Forwarded-Host nie jest zaufany', function () {
    expect(TrustedProxyConfig::parse(null))->toBeNull()
        ->and(TrustedProxyConfig::parse('  '))->toBeNull()
        ->and(TrustedProxyConfig::parse('173.245.48.0/20, 103.21.244.0/22'))->toBe(['173.245.48.0/20', '103.21.244.0/22'])
        ->and(TrustedProxyConfig::parse('*'))->toBe('*')
        ->and(TrustedProxyConfig::headers() & Request::HEADER_X_FORWARDED_HOST)->toBe(0)
        ->and(TrustedProxyConfig::headers() & Request::HEADER_X_FORWARDED_FOR)->not->toBe(0)
        ->and(TrustedProxyConfig::headers() & Request::HEADER_X_FORWARDED_PROTO)->not->toBe(0);
});

it('S3: od zaufanego proxy bierzemy IP klienta, ale nie nazwę hosta', function () {
    TrustProxies::at(['10.6.6.6']);
    TrustProxies::withHeaders(TrustedProxyConfig::headers());
    Route::get('/__probe', fn (Request $request) => response()->json(['ip' => $request->ip(), 'host' => $request->getHost()]));

    $json = $this->withServerVariables(['REMOTE_ADDR' => '10.6.6.6'])
        ->withHeaders(['X-Forwarded-For' => '9.9.9.9', 'X-Forwarded-Host' => 'evil.example'])
        ->get('/__probe')->json();

    expect($json['ip'])->toBe('9.9.9.9')->and($json['host'])->not->toBe('evil.example');

    // Obcy adres (spoofing bez zaufanego proxy) nie zmienia IP.
    $spoofed = $this->withServerVariables(['REMOTE_ADDR' => '10.7.7.7'])
        ->withHeaders(['X-Forwarded-For' => '9.9.9.9'])
        ->get('/__probe')->json();
    expect($spoofed['ip'])->toBe('10.7.7.7');

    TrustProxies::flushState();
});

// ---------------------------------------------------------------- S4: AuthenticateSession

it('S4: zmiana hasła poza sesją (np. reset) wylogowuje sesję także przy sterowniku innym niż database', function () {
    expect(config('session.driver'))->not->toBe('database');
    $user = User::factory()->owner()->create(['password' => AUDIT_PASSWORD]);

    $this->actingAs($user)->get('/pulpit')->assertOk();

    // Reset hasła z innego urządzenia: zmienia się skrót w bazie, sesja napastnika ma stary.
    User::whereKey($user->id)->update(['password' => Hash::make('zupelnie-nowe-haslo-42')]);
    $user->refresh();

    $this->get('/pulpit')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('S4: zmiana hasła przez zalogowanego nie wylogowuje bieżącej sesji', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['password' => AUDIT_PASSWORD]);

    $this->actingAs($user)->get('/pulpit')->assertOk();
    $this->post('/konto/haslo', ['current_password' => AUDIT_PASSWORD, 'password' => 'zupelnie-nowe-haslo-42'])
        ->assertRedirect(route('account.security'));

    $this->get('/pulpit')->assertOk();
    $this->assertAuthenticated();
});

// ---------------------------------------------------------------- S5: strażnik konfiguracji produkcyjnej

it('S5: strażnik zgłasza każdą niebezpieczną wartość konfiguracji', function (array $override, string $fragment) {
    validProductionConfig();
    expect((new ProductionConfigGuard)->violations())->toBe([]);

    config($override);

    expect(implode(' ', (new ProductionConfigGuard)->violations()))->toContain($fragment);
})->with([
    'debug' => [['app.debug' => true], 'APP_DEBUG'],
    'turnstile wyłączony' => [['services.turnstile.enabled' => false], 'TURNSTILE_ENABLED'],
    'klucz testowy site' => [['services.turnstile.site_key' => '1x00000000000000000000AA'], 'TURNSTILE_SITE_KEY'],
    'klucz testowy secret' => [['services.turnstile.secret' => '1x0000000000000000000000000000000AA'], 'TURNSTILE_SECRET_KEY'],
    'klucz "zawsze odrzucaj"' => [['services.turnstile.secret' => '2x0000000000000000000000000000000AA'], 'TURNSTILE_SECRET_KEY'],
    'brak klucza' => [['services.turnstile.site_key' => null], 'TURNSTILE_SITE_KEY'],
    'kolejka sync' => [['queue.default' => 'sync'], 'QUEUE_CONNECTION'],
    'mailer log' => [['mail.default' => 'log'], 'MAIL_MAILER'],
    'mailer array' => [['mail.default' => 'array'], 'MAIL_MAILER'],
    'ciasteczko bez Secure' => [['session.secure' => null], 'SESSION_SECURE_COOKIE'],
    'HIBP wyłączone' => [['auth.check_pwned' => false], 'AUTH_CHECK_PWNED'],
    'brak hasła archiwum kopii' => [['backup.backup.password' => null], 'BACKUP_ARCHIVE_PASSWORD'],
    'za krótkie hasło archiwum kopii' => [['backup.backup.password' => 'krotkie'], 'BACKUP_ARCHIVE_PASSWORD'],
]);

it('S5: brak adresów e-mail dla alarmów kopii i kontaktu Nominatim NIE blokuje startu (decyzja właściciela 30.10.2026)', function () {
    validProductionConfig();
    config(['geocoding.contact' => null, 'backup.notifications.mail.to' => null]);

    expect((new ProductionConfigGuard)->violations())->toBe([]);
});

it('S5: aplikacja na produkcji nie startuje z konfiguracją deweloperską', function (array $override) {
    validProductionConfig();
    config($override);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new AppServiceProvider(app()))->boot())->toThrow(RuntimeException::class, 'Niebezpieczna konfiguracja');
})->with([
    [['app.debug' => true]],
    [['services.turnstile.site_key' => '1x00000000000000000000AA']],
    [['queue.default' => 'sync']],
    [['mail.default' => 'log']],
    [['session.secure' => false]],
    [['auth.check_pwned' => false]],
]);

it('S5: poprawna konfiguracja produkcyjna startuje i wymusza https oraz APP_URL w adresach', function () {
    validProductionConfig();
    config(['app.url' => 'https://kontroladomu.pl']);
    app()->detectEnvironment(fn () => 'production');

    (new AppServiceProvider(app()))->boot();

    expect(URL::to('/regulamin'))->toBe('https://kontroladomu.pl/regulamin');
    URL::forceRootUrl(null);
    URL::forceScheme(null);
});

it('S5: plik .env.production.example istnieje, ma bezpieczne wartości i nie zawiera sekretów', function () {
    $path = base_path('.env.production.example');
    expect(file_exists($path))->toBeTrue();
    $env = (string) file_get_contents($path);

    expect($env)->toContain('APP_ENV=production')
        ->and($env)->toContain('APP_DEBUG=false')
        ->and($env)->toContain('QUEUE_CONNECTION=database')
        ->and($env)->toContain('SESSION_SECURE_COOKIE=true')
        ->and($env)->toContain('SESSION_DRIVER=database')
        ->and($env)->toContain('AUTH_CHECK_PWNED=true')
        ->and($env)->toContain('TURNSTILE_ENABLED=true')
        ->and($env)->toMatch('/^MAIL_MAILER=smtp$/m')
        ->and($env)->not->toContain('1x0000')
        ->and($env)->toMatch('/^APP_KEY=$/m')
        ->and($env)->toMatch('/^DB_PASSWORD=$/m')
        ->and($env)->toMatch('/^TURNSTILE_SECRET_KEY=$/m')
        ->and($env)->not->toMatch('/^TRUSTED_PROXIES=\*$/m');
});

// ---------------------------------------------------------------- S6: zaufane hosty

it('S6: tylko host z APP_URL jest zaufany (obcy Host i X-Forwarded-Host są odrzucane)', function () {
    config(['app.url' => 'https://kontroladomu.pl']);
    $hosts = (new TrustHosts(app()))->hosts();

    expect($hosts)->toBe(['^kontroladomu\.pl$']);

    Request::setTrustedHosts($hosts);
    expect(Request::create('https://kontroladomu.pl/')->getHost())->toBe('kontroladomu.pl')
        ->and(fn () => Request::create('https://evil.example/')->getHost())->toThrow(SuspiciousOperationException::class)
        ->and(fn () => Request::create('https://sub.kontroladomu.pl/')->getHost())->toThrow(SuspiciousOperationException::class);
    Request::setTrustedHosts([]);
});

it('S6: żądanie z obcym nagłówkiem Host na produkcji dostaje 400, a z hostem z APP_URL działa', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://evil.example/')->assertStatus(400);
    $this->get('http://kontroladomu.test/')->assertOk();
    Request::setTrustedHosts([]);
});

// ---------------------------------------------------------------- N2, N5, axios

it('N2: udane logowanie nie zeruje licznika błędów adresu IP (nie da się go „resetować" własnym kontem)', function () {
    $counter = app(LoginFailureCounter::class);
    User::factory()->owner()->create(['email' => 'moje@example.pl', 'password' => AUDIT_PASSWORD]);

    $this->post('/logowanie', ['email' => 'moje@example.pl', 'password' => 'zle-haslo-zle-haslo']);
    $this->post('/logowanie', ['email' => 'moje@example.pl', 'password' => AUDIT_PASSWORD])->assertRedirect('/pulpit');

    expect($counter->count('127.0.0.1'))->toBe(1)
        ->and($counter->accountCount('moje@example.pl'))->toBe(0);
});

it('N5: po resecie hasła przez link właściciel dostaje wiadomość o zmianie hasła', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl']);

    $this->post('/nowe-haslo', [
        'token' => Password::createToken($user), 'email' => 'jan@example.pl', 'password' => 'nowe-zielone-haslo-123',
    ])->assertRedirect(route('login'));

    Notification::assertSentTo($user, PasswordChangedNotification::class);
});

it('N12: nieużywany axios został usunięty z projektu', function () {
    expect(file_exists(resource_path('js/bootstrap.js')))->toBeFalse()
        ->and((string) file_get_contents(base_path('package.json')))->not->toContain('axios');
});
