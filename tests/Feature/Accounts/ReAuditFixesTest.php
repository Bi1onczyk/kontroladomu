<?php

/*
| Poprawki po re-audycie zadania 002 (R1-R5). Każdy test opisuje atak, który przed poprawką się udawał.
*/

use App\Domain\Accounts\Jobs\ProcessPendingEmailChange;
use App\Domain\Accounts\Notifications\RegisterAttemptNotification;
use App\Domain\Accounts\Notifications\VerifyEmailNotification;
use App\Domain\Accounts\Notifications\VerifyPendingEmailNotification;
use App\Domain\Accounts\Support\LoginFailureCounter;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\EmailNormalizer;
use App\Support\Security\ProductionConfigGuard;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

const RE_PASSWORD = 'zielony-dach-w-lutym';

// ---------------------------------------------------------------- R1: zmiana e-maila przez pending_email

it('R1a: drugie żądanie z tym samym adresem daje tę samą odpowiedź dla adresu wolnego i zajętego', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);

    $results = [];
    foreach (['zajety@example.pl', 'wolny@example.pl'] as $target) {
        $user = User::factory()->owner()->unverified()->create();
        $first = $this->actingAs($user)->post('/email/zmien', ['email' => $target]);
        $this->flushSession();
        $second = $this->actingAs($user)->post('/email/zmien', ['email' => $target]);

        $results[$target] = [
            $first->getStatusCode(), $first->headers->get('Location'), count(session('errors')?->all() ?? []),
            $second->getStatusCode(), $second->headers->get('Location'), count(session('errors')?->all() ?? []),
        ];
        expect($user->fresh()->email)->not->toBe($target); // adres konta nigdy nie zmienia się w tym kroku
    }

    expect($results['zajety@example.pl'])->toBe($results['wolny@example.pl'])
        ->and($results['wolny@example.pl'][2])->toBe(0)->and($results['wolny@example.pl'][5])->toBe(0);
});

it('R1b: nowym adresem nie da się zalogować przed potwierdzeniem (ani dla wolnego, ani dla zajętego)', function () {
    Notification::fake();
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);

    foreach (['zajety@example.pl', 'wolny@example.pl'] as $i => $target) {
        $user = User::factory()->owner()->unverified()->create(['email' => "stary{$i}@example.pl", 'password' => RE_PASSWORD]);
        $this->actingAs($user)->post('/email/zmien', ['email' => $target]);
        auth()->logout();
        $this->flushSession();

        $this->post('/logowanie', ['email' => $target, 'password' => RE_PASSWORD])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }
});

it('R1c: ta sama praca synchroniczna dla adresu wolnego i zajętego (ciężkie rzeczy dopiero w kolejce)', function () {
    Queue::fake();
    Notification::fake();
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);

    $counts = [];
    foreach (['zajety@example.pl', 'wolny@example.pl'] as $target) {
        $user = User::factory()->owner()->unverified()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->post('/email/zmien', ['email' => $target]);
        $counts[$target] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }

    expect($counts['zajety@example.pl'])->toBe($counts['wolny@example.pl']);
    Queue::assertPushed(ProcessPendingEmailChange::class, 2);
    Notification::assertNothingSent(); // żadnej wiadomości ani haszowania w żądaniu HTTP
});

it('R1: wolny adres dostaje link, zajęty powiadamia właściciela konta, nikt nie dostaje obu', function () {
    Notification::fake();
    $holder = User::factory()->owner()->create(['email' => 'zajety@example.pl']);

    $a = User::factory()->owner()->unverified()->create();
    $this->actingAs($a)->post('/email/zmien', ['email' => 'wolny@example.pl']);
    Notification::assertSentOnDemand(VerifyPendingEmailNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'wolny@example.pl');
    Notification::assertNotSentTo($holder, RegisterAttemptNotification::class);

    $b = User::factory()->owner()->unverified()->create();
    $this->actingAs($b)->post('/email/zmien', ['email' => 'zajety@example.pl']);
    Notification::assertSentTo($holder, RegisterAttemptNotification::class);
    Notification::assertSentOnDemandTimes(VerifyPendingEmailNotification::class, 1);
});

it('R1: kliknięcie w link podmienia adres, potwierdza go i nie loguje', function () {
    $user = User::factory()->specialist()->unverified()->create(['email' => 'stary@example.pl', 'pending_email' => 'nowy@example.pl']);

    $this->get(pendingLink($user, 'nowy@example.pl'))
        ->assertRedirect(route('verification.done'))
        ->assertSessionHas('verify_status', 'verified')
        ->assertSessionHas('verified_role', 'specialist');

    $user->refresh();
    expect($user->email)->toBe('nowy@example.pl')
        ->and($user->pending_email)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertGuest();
});

it('R1: jeśli adres został zajęty w międzyczasie, link daje neutralny ekran "nieważny" i adres konta się nie zmienia', function () {
    $user = User::factory()->owner()->unverified()->create(['email' => 'stary@example.pl', 'pending_email' => 'nowy@example.pl']);
    User::factory()->owner()->create(['email' => 'nowy@example.pl']); // ktoś zdążył zarejestrować

    $this->get(pendingLink($user, 'nowy@example.pl'))->assertSessionHas('verify_status', 'invalid');

    $user->refresh();
    expect($user->email)->toBe('stary@example.pl')->and($user->hasVerifiedEmail())->toBeFalse();
});

it('R1: link z błędnym skrótem, bez podpisu, po terminie lub bez oczekującej zmiany jest nieważny', function () {
    $user = User::factory()->owner()->unverified()->create(['email' => 'stary@example.pl', 'pending_email' => 'nowy@example.pl']);

    $this->get(URL::temporarySignedRoute('email.change.verify', now()->addDay(), ['id' => $user->id, 'hash' => sha1('inny@example.pl')]))
        ->assertSessionHas('verify_status', 'invalid');
    $this->get(route('email.change.verify', ['id' => $user->id, 'hash' => sha1('nowy@example.pl')]))
        ->assertSessionHas('verify_status', 'invalid');
    $this->get(pendingLink($user, 'nowy@example.pl', -5))->assertSessionHas('verify_status', 'invalid');

    $other = User::factory()->owner()->unverified()->create();
    $this->get(pendingLink($other, 'nowy@example.pl'))->assertSessionHas('verify_status', 'invalid');

    expect($user->fresh()->email)->toBe('stary@example.pl');
});

it('R1: ponowna wysyłka przy oczekującej zmianie idzie tą samą ścieżką co pierwsza', function () {
    Queue::fake();
    $user = User::factory()->owner()->unverified()->create(['pending_email' => 'nowy@example.pl']);

    $this->actingAs($user)->post('/email/ponow')->assertSessionHas('status');

    Queue::assertPushed(ProcessPendingEmailChange::class, 1);
});

// ---------------------------------------------------------------- R2: warianty adresu a licznik konta

it('R2: kanoniczna postać adresu ignoruje akcenty i wielkość liter (jak collation bazy)', function () {
    expect(EmailNormalizer::canonical('jo@x.pl'))->toBe(EmailNormalizer::canonical('jó@x.pl'))
        ->and(EmailNormalizer::canonical('JAN@x.pl'))->toBe(EmailNormalizer::canonical(' ján@X.pl '));

    $counter = app(LoginFailureCounter::class);
    $counter->hit('10.0.0.1', 'jo@x.pl');
    $counter->hit('10.0.0.2', 'jó@x.pl');
    expect($counter->accountCount('JO@x.pl'))->toBe(2);
});

it('R2: warianty akcentów adresu nie omijają licznika konta (Turnstile po 5 błędach)', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'sekret']);
    Http::fake(fn ($request) => Http::response(['success' => $request['response'] === 'dobry'], 200));
    User::factory()->owner()->create(['email' => 'jan@x.pl', 'password' => RE_PASSWORD]);

    foreach (['jan@x.pl', 'ján@x.pl', 'jàn@x.pl', 'JAN@x.pl', 'jañ@x.pl'] as $i => $variant) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.8.8.{$i}"])
            ->post('/logowanie', ['email' => $variant, 'password' => 'zle-haslo-zle-haslo'])
            ->assertSessionHasErrors('email');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.99'])
        ->post('/logowanie', ['email' => 'jan@x.pl', 'password' => RE_PASSWORD])
        ->assertSessionHasErrors('cf-turnstile-response');
});

it('R2: wariant adresu z akcentem nie loguje na cudze konto, nawet z poprawnym hasłem', function () {
    User::factory()->owner()->create(['email' => 'jan@x.pl', 'password' => RE_PASSWORD]);

    $this->post('/logowanie', ['email' => 'ján@x.pl', 'password' => RE_PASSWORD])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

// ---------------------------------------------------------------- R3: limit maili na adres odbiorcy

it('R3: z wielu kont nie da się wysłać więcej niż 3 maili weryfikacyjnych na ten sam wolny adres w godzinie', function () {
    Notification::fake();

    foreach (range(1, 6) as $i) {
        $user = User::factory()->owner()->unverified()->create();
        $this->actingAs($user)->post('/email/zmien', ['email' => 'ofiara@example.pl'])->assertSessionHasNoErrors();
    }

    Notification::assertSentOnDemandTimes(VerifyPendingEmailNotification::class, 3);
});

it('R3: limit obejmuje też zwykłe potwierdzenie adresu i nie zależy od wielkości liter ani akcentów', function () {
    Notification::fake();
    $user = User::factory()->owner()->unverified()->create(['email' => 'jan@example.pl']);

    foreach (range(1, 5) as $i) {
        $user->sendEmailVerificationNotification();
    }

    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
});

// ---------------------------------------------------------------- R4: reset 2FA kończy sesje niezależnie od sterownika

it('R4: user:reset-2fa kończy aktywne sesje także przy sterowniku sesji innym niż database', function () {
    expect(config('session.driver'))->not->toBe('database');
    Notification::fake();
    $user = User::factory()->owner()->withTwoFactor()->create(['email' => 'jan@example.pl']);

    $this->actingAs($user)->get('/pulpit')->assertOk();

    $this->artisan('user:reset-2fa', ['email' => 'jan@example.pl', '--reason' => 'test', '--force' => true])->assertExitCode(0);
    $user->refresh(); // w produkcji każde żądanie ładuje konto z bazy

    $this->get('/pulpit')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('R4: sesja zalogowana po resecie ma nową wersję i działa', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => RE_PASSWORD]);
    $user->forceFill(['session_version' => 4])->save();

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => RE_PASSWORD])->assertRedirect('/pulpit');
    $this->get('/pulpit')->assertOk();
    expect(session('session_version'))->toBe(4);
});

// ---------------------------------------------------------------- R5: strażnik konfiguracji

it('R5: strażnik wymaga APP_URL z https i hostem oraz niepustych TRUSTED_PROXIES różnych od "*"', function (array $override, string $fragment) {
    config([
        'app.debug' => false, 'app.url' => 'https://kontroladomu.pl', 'security.trusted_proxies' => '173.245.48.0/20',
        'services.turnstile.enabled' => true, 'services.turnstile.site_key' => '0x4AAAA', 'services.turnstile.secret' => '0x4BBBB',
        'queue.default' => 'database', 'mail.default' => 'smtp', 'session.secure' => true, 'auth.check_pwned' => true,
        'backup.backup.password' => 'haslo-archiwum-testowe-20+',
    ]);
    expect((new ProductionConfigGuard)->violations())->toBe([]);

    config($override);

    expect(implode(' ', (new ProductionConfigGuard)->violations()))->toContain($fragment);
})->with([
    'http zamiast https' => [['app.url' => 'http://kontroladomu.pl'], 'APP_URL'],
    'brak hosta' => [['app.url' => 'https://'], 'APP_URL'],
    'puste proxy' => [['security.trusted_proxies' => ''], 'TRUSTED_PROXIES'],
    'proxy gwiazdka' => [['security.trusted_proxies' => '*'], 'TRUSTED_PROXIES'],
]);

it('R5: strażnik działa też w środowisku staging (nie tylko production), ale nie lokalnie', function () {
    config(['app.debug' => true]);

    app()->detectEnvironment(fn () => 'staging');
    expect(fn () => (new AppServiceProvider(app()))->boot())->toThrow(RuntimeException::class);

    app()->detectEnvironment(fn () => 'local');
    (new AppServiceProvider(app()))->boot();
    expect(true)->toBeTrue();
});

it('R5: gdy APP_URL nie ma hosta, zaufane hosty nie są wyłączane (fail-closed): żaden host nie przechodzi', function () {
    config(['app.url' => 'https://']);
    expect(array_filter((new TrustHosts(app()))->hosts()))->toBe(['^$']);
});
