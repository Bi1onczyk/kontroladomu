<?php

use App\Domain\Accounts\Notifications\TwoFactorResetNotification;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Turnstile\TurnstileVerifier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

it('dodaje komplet nagłówków bezpieczeństwa z nonce w CSP', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+' https:\/\/challenges\.cloudflare\.com/")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->not->toContain('unsafe-eval')
        ->and($csp)->not->toContain('unsafe-inline');
});

it('używa innego nonce w każdym żądaniu i dodaje nagłówki do błędów 404', function () {
    $a = $this->get('/')->headers->get('Content-Security-Policy');
    $b = $this->get('/nie-ma-takiej-strony')->assertNotFound();

    expect($b->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($b->headers->get('Content-Security-Policy'))->not->toBe($a);
});

it('dopuszcza serwer Vite w CSP tylko w środowisku lokalnym', function () {
    app()->detectEnvironment(fn () => 'local');
    expect($this->get('/')->headers->get('Content-Security-Policy'))->toContain('http://localhost:5173');

    app()->detectEnvironment(fn () => 'testing');
    expect($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('5173');
});

it('zakazuje cache dla pulpitu', function () {
    $response = $this->actingAs(User::factory()->owner()->create())->get('/pulpit');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('weryfikator Turnstile odmawia przy braku tokenu, błędzie sieci i statusie innym niż 200', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 's']);
    $verifier = app(TurnstileVerifier::class);

    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;

        return match ($calls) {
            1 => Http::response('', 500),
            2 => throw new ConnectionException('timeout'),
            default => Http::response(['success' => true], 200),
        };
    });

    expect($verifier->verify(null))->toBeFalse()->and($verifier->verify(''))->toBeFalse()
        ->and($calls)->toBe(0)
        ->and($verifier->verify('token'))->toBeFalse()
        ->and($verifier->verify('token'))->toBeFalse()
        ->and($verifier->verify('token', '1.2.3.4'))->toBeTrue();
});

it('weryfikator Turnstile przepuszcza, gdy jest wyłączony (lokalnie i w testach)', function () {
    config(['services.turnstile.enabled' => false]);

    expect(app(TurnstileVerifier::class)->verify(null))->toBeTrue();
});

it('aplikacja nie startuje na produkcji z wyłączonym Turnstile', function () {
    config(['services.turnstile.enabled' => false]);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new AppServiceProvider(app()))->boot())->toThrow(RuntimeException::class);
});

it('przekierowuje wygasły token CSRF na logowanie z komunikatem', function () {
    Route::post('/test-csrf', fn () => throw new TokenMismatchException)->middleware('web');

    $this->post('/test-csrf')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('auth.session_expired'));
});

it('ma wszystkie trasy z kontraktu o właściwych nazwach i adresach', function () {
    $expected = [
        'login' => '/logowanie', 'login.store' => '/logowanie', 'logout' => '/wyloguj',
        'register' => '/rejestracja', 'register.owner' => '/rejestracja/wlasciciel',
        'register.specialist' => '/rejestracja/fachowiec', 'register.owner.store' => '/rejestracja/wlasciciel',
        'register.specialist.store' => '/rejestracja/fachowiec', 'register.sent' => '/rejestracja/sprawdz-skrzynke',
        'password.request' => '/nie-pamietam-hasla', 'password.email' => '/nie-pamietam-hasla',
        'password.reset' => '/nowe-haslo/{token}', 'password.store' => '/nowe-haslo',
        'verification.notice' => '/email/potwierdz', 'verification.send' => '/email/ponow',
        'verification.verify' => '/email/potwierdz/{id}/{hash}', 'verification.done' => '/email/potwierdzono',
        'email.change' => '/email/zmien', 'email.change.store' => '/email/zmien',
        'two-factor.login' => '/logowanie/kod', 'two-factor.login.store' => '/logowanie/kod',
        'password.confirm' => '/potwierdz-haslo', 'password.confirm.store' => '/potwierdz-haslo',
        'account.security' => '/konto/bezpieczenstwo', 'two-factor.enable' => '/konto/bezpieczenstwo/2fa',
        'two-factor.confirm' => '/konto/bezpieczenstwo/2fa/potwierdz', 'two-factor.disable' => '/konto/bezpieczenstwo/2fa',
        'two-factor.codes.download' => '/konto/bezpieczenstwo/kody-awaryjne.txt',
        'two-factor.codes.regenerate' => '/konto/bezpieczenstwo/kody-awaryjne',
        'two-factor.reminder.dismiss' => '/konto/przypomnienie-2fa', 'password.change' => '/konto/haslo',
        'dashboard' => '/pulpit', 'terms' => '/regulamin', 'privacy' => '/polityka-prywatnosci',
        'cookies' => '/polityka-cookies', 'contact' => '/kontakt',
    ];

    foreach ($expected as $name => $uri) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull("brak trasy {$name}")
            ->and('/'.ltrim($route->uri(), '/'))->toBe($uri);
    }
});

it('tworzy administratora komendą z hasłem w ukrytym polu', function () {
    $this->artisan('admin:create', ['email' => 'Admin@Example.pl', '--name' => 'Ada Admin'])
        ->expectsQuestion('Hasło (min. 12 znaków)', 'bardzo-dlugie-haslo-admina')
        ->expectsQuestion('Powtórz hasło', 'bardzo-dlugie-haslo-admina')
        ->assertExitCode(0);

    $admin = User::where('email', 'admin@example.pl')->firstOrFail();
    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->requiresTwoFactor())->toBeTrue()
        ->and($admin->hasConfirmedTwoFactor())->toBeFalse();

    $entry = Activity::where('event', 'admin_created')->firstOrFail();
    expect($entry->log_name)->toBe('security_admin')
        ->and(json_encode($entry->properties))->not->toContain('bardzo-dlugie');
});

it('komenda admin:create odmawia dla istniejącego e-maila i słabego hasła', function () {
    User::factory()->owner()->create(['email' => 'jest@example.pl']);

    $this->artisan('admin:create', ['email' => 'jest@example.pl', '--name' => 'X'])->assertExitCode(1);

    $this->artisan('admin:create', ['email' => 'nowy@example.pl', '--name' => 'X'])
        ->expectsQuestion('Hasło (min. 12 znaków)', 'krotkie')
        ->expectsQuestion('Powtórz hasło', 'krotkie')
        ->assertExitCode(1);

    expect(User::where('email', 'nowy@example.pl')->exists())->toBeFalse();
});

it('resetuje 2FA komendą: czyści dane, kończy sesje, wysyła mail i zapisuje powód', function () {
    Notification::fake();
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl']);

    $this->artisan('user:reset-2fa', ['email' => 'jan@example.pl', '--reason' => 'zgłoszenie 7, oddzwonienie', '--force' => true])
        ->assertExitCode(0);

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->hasConfirmedTwoFactor())->toBeFalse();
    Notification::assertSentTo($user, TwoFactorResetNotification::class);

    $entry = Activity::where('event', 'two_factor_reset')->firstOrFail();
    expect($entry->log_name)->toBe('security_admin')
        ->and($entry->subject_id)->toBe($user->id)
        ->and($entry->properties['reason'])->toBe('zgłoszenie 7, oddzwonienie');
});

it('komenda user:reset-2fa wymaga istniejącego konta i powodu', function () {
    $this->artisan('user:reset-2fa', ['email' => 'brak@example.pl', '--reason' => 'x', '--force' => true])->assertExitCode(1);

    User::factory()->owner()->withTwoFactor()->create(['email' => 'jan@example.pl']);
    $this->artisan('user:reset-2fa', ['email' => 'jan@example.pl', '--force' => true])
        ->expectsQuestion('Powód resetu (np. numer zgłoszenia, sposób weryfikacji tożsamości)', '')
        ->assertExitCode(1);
});

it('usuwa konta niepotwierdzone starsze niż 7 dni', function () {
    $old = User::factory()->owner()->unverified()->create(['created_at' => now()->subDays(8)]);
    $fresh = User::factory()->owner()->unverified()->create();
    $verifiedOld = User::factory()->owner()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('accounts:prune-unverified')->assertExitCode(0);

    expect(User::find($old->id))->toBeNull()
        ->and(User::find($fresh->id))->not->toBeNull()
        ->and(User::find($verifiedOld->id))->not->toBeNull();
});

it('czyści dziennik: 90 dni zwykłe wpisy, 12 miesięcy działania administratora', function () {
    $make = function (string $log, int $daysAgo) {
        $activity = activity($log)->log('x');
        $activity->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $activity->id;
    };
    $regularOld = $make('security', 100);
    $regularNew = $make('security', 10);
    $adminMid = $make('security_admin', 200);
    $adminOld = $make('security_admin', 400);

    $this->artisan('activity:prune')->assertExitCode(0);

    expect(Activity::find($regularOld))->toBeNull()
        ->and(Activity::find($regularNew))->not->toBeNull()
        ->and(Activity::find($adminMid))->not->toBeNull()
        ->and(Activity::find($adminOld))->toBeNull();
});

it('dopisuje komplet zadań do harmonogramu', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command)->implode(' | ');

    expect($commands)->toContain('queue:work --stop-when-empty')
        ->and($commands)->toContain('accounts:prune-unverified')
        ->and($commands)->toContain('activity:prune');
});
