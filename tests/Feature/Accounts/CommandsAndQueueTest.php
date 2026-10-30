<?php

/*
| Komendy konsolowe (admin:create, user:reset-2fa, accounts:prune-unverified), kolejka maili i wydajność pulpitu.
| Uwaga: w testach AuditLogger zapisuje ip/user_agent zamiast operator/host (gałąź runningUnitTests),
| więc pól operator/host nie da się tu sprawdzić - zostają do kontroli ręcznej.
*/

use App\Domain\Accounts\Notifications\TwoFactorResetNotification;
use App\Domain\Accounts\Notifications\VerifyEmailNotification;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

// ---------------------------------------------------------------- admin:create

it('admin:create odrzuca różne hasła, zły e-mail i hasło krótsze niż 12 znaków', function () {
    $this->artisan('admin:create', ['email' => 'a@example.pl', '--name' => 'Ada'])
        ->expectsQuestion('Hasło (min. 12 znaków)', 'bardzo-dlugie-haslo-1')
        ->expectsQuestion('Powtórz hasło', 'bardzo-dlugie-haslo-2')
        ->assertExitCode(1);

    $this->artisan('admin:create', ['email' => 'to-nie-email', '--name' => 'Ada'])
        ->expectsQuestion('Hasło (min. 12 znaków)', 'bardzo-dlugie-haslo-1')
        ->expectsQuestion('Powtórz hasło', 'bardzo-dlugie-haslo-1')
        ->assertExitCode(1);

    $this->artisan('admin:create', ['email' => 'b@example.pl', '--name' => 'Ada'])
        ->expectsQuestion('Hasło (min. 12 znaków)', '12345678901')
        ->expectsQuestion('Powtórz hasło', '12345678901')
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

it('admin:create tworzy tylko rolę admin, zapisuje hasło jako hash i wpis w logu security_admin bez sprawcy', function () {
    $this->artisan('admin:create', ['email' => '  Ada@Example.PL ', '--name' => 'Ada Admin'])
        ->expectsQuestion('Hasło (min. 12 znaków)', 'bardzo-dlugie-haslo-admina')
        ->expectsQuestion('Powtórz hasło', 'bardzo-dlugie-haslo-admina')
        ->assertExitCode(0);

    $admin = User::firstOrFail();
    expect($admin->email)->toBe('ada@example.pl')
        ->and($admin->getRoleNames()->all())->toBe(['admin'])
        ->and($admin->getRawOriginal('password'))->not->toContain('bardzo-dlugie')
        ->and($admin->marketing_consent_at)->toBeNull()
        ->and($admin->nip)->toBeNull();

    $entry = Activity::where('event', 'admin_created')->firstOrFail();
    expect($entry->causer_id)->toBeNull()->and($entry->subject_id)->toBe($admin->id);
});

it('administrator utworzony komendą loguje się i jest kierowany do obowiązkowego kreatora 2FA', function () {
    $this->artisan('admin:create', ['email' => 'ada@example.pl', '--name' => 'Ada Admin'])
        ->expectsQuestion('Hasło (min. 12 znaków)', 'bardzo-dlugie-haslo-admina')
        ->expectsQuestion('Powtórz hasło', 'bardzo-dlugie-haslo-admina')
        ->assertExitCode(0);

    $this->post('/logowanie', ['email' => 'ada@example.pl', 'password' => 'bardzo-dlugie-haslo-admina'])->assertRedirect('/pulpit');
    $this->get('/pulpit')->assertRedirect(route('account.security'));
});

// ---------------------------------------------------------------- user:reset-2fa

it('user:reset-2fa bez potwierdzenia niczego nie zmienia', function () {
    Notification::fake();
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl']);

    $this->artisan('user:reset-2fa', ['email' => 'jan@example.pl', '--reason' => 'test'])
        ->expectsConfirmation('Zresetować weryfikację dwuetapową i wylogować użytkownika ze wszystkich urządzeń?', 'no')
        ->assertExitCode(1);

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue();
    Notification::assertNothingSent();
    expect(Activity::where('event', 'two_factor_reset')->count())->toBe(0);
});

it('user:reset-2fa kończy sesje, zeruje blokadę, rotuje token i kieruje fachowca z powrotem do kreatora', function () {
    Notification::fake();
    config(['session.driver' => 'database']);
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $token = $user->remember_token;
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    // Blokada po 5 błędach...
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    foreach (range(1, 5) as $i) {
        $this->post('/logowanie/kod', ['code' => '77777'.$i]);
    }
    $this->get('/logowanie/kod')->assertStatus(429);

    $this->artisan('user:reset-2fa', ['email' => 'JAN@example.pl', '--reason' => 'zgłoszenie 12', '--force' => true])->assertExitCode(0);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->remember_token)->not->toBe($token);

    // ...którą reset zdejmuje, a logowanie trafia do kreatora (bez pytania o kod).
    $this->flushSession();
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD])->assertRedirect('/pulpit');
    $this->get('/pulpit')->assertRedirect(route('account.security'));
});

it('user:reset-2fa wysyła właściwy wariant maila: dla fachowca "ustaw od nowa", dla właściciela "włącz ponownie"', function () {
    Notification::fake();
    $specialist = User::factory()->specialist()->withTwoFactor()->create(['email' => 'f@example.pl']);
    $owner = User::factory()->owner()->withTwoFactor()->create(['email' => 'w@example.pl']);

    foreach (['f@example.pl', 'w@example.pl'] as $email) {
        $this->artisan('user:reset-2fa', ['email' => $email, '--reason' => 'test', '--force' => true])->assertExitCode(0);
    }

    $variants = [];
    foreach ([$specialist, $owner] as $user) {
        Notification::assertSentTo($user, TwoFactorResetNotification::class, function ($n) use ($user, &$variants) {
            $variants[$user->email] = (string) $n->toMail($user)->render();

            return true;
        });
    }

    expect($variants['f@example.pl'])->not->toBe($variants['w@example.pl'])
        ->and($variants['f@example.pl'])->not->toContain('mail.');
});

it('user:reset-2fa zapisuje powód i obiekt w logu administratora, nie zapisując sekretów', function () {
    Notification::fake();
    $user = User::factory()->owner()->withTwoFactor()->create(['email' => 'jan@example.pl']);
    $secret = (string) $user->two_factor_secret;

    $this->artisan('user:reset-2fa', ['email' => 'jan@example.pl', '--reason' => 'telefon zgubiony', '--force' => true])->assertExitCode(0);

    $entry = Activity::where('event', 'two_factor_reset')->firstOrFail();
    expect($entry->log_name)->toBe('security_admin')
        ->and($entry->subject_id)->toBe($user->id)
        ->and($entry->properties['reason'])->toBe('telefon zgubiony')
        ->and(json_encode($entry->properties))->not->toContain($secret);
});

// ---------------------------------------------------------------- accounts:prune-unverified

it('accounts:prune-unverified: granica 7 dni, usuwa też przypisania ról, nie rusza potwierdzonych', function () {
    $six = User::factory()->owner()->unverified()->create(['created_at' => now()->subDays(6)]);
    $eight = User::factory()->specialist()->unverified()->create(['created_at' => now()->subDays(8)]);
    $verified = User::factory()->owner()->create(['created_at' => now()->subDays(100)]);

    $this->artisan('accounts:prune-unverified')->assertExitCode(0);

    expect(User::find($six->id))->not->toBeNull()
        ->and(User::find($eight->id))->toBeNull()
        ->and(User::find($verified->id))->not->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $eight->id)->count())->toBe(0);
});

it('harmonogram zawiera czyszczenie tokenów, kont, dziennika i nieudanych zadań', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' | ');

    foreach (['auth:clear-resets', 'accounts:prune-unverified', 'activity:prune', 'queue:prune-failed', 'queue:work'] as $command) {
        expect($commands)->toContain($command);
    }
});

// ---------------------------------------------------------------- kolejka i maile

it('rejestracja wysyła użytkownikowi dokładnie jeden mail weryfikacyjny', function () {
    Notification::fake();

    $this->post('/rejestracja/wlasciciel', ownerPayload())->assertRedirect(route('register.sent'));

    Notification::assertSentToTimes(User::firstOrFail(), VerifyEmailNotification::class, 1);
});

it('rejestracja kolejkuje dokładnie jedną wiadomość (nic nie wychodzi synchronicznie)', function () {
    Queue::fake();

    $this->post('/rejestracja/wlasciciel', ownerPayload())->assertRedirect(route('register.sent'));

    Queue::assertPushed(SendQueuedNotifications::class, 1);
});

it('powtórne kliknięcie "wyślij ponownie" w ciągu minuty nie kolejkuje drugiego maila', function () {
    Queue::fake();
    $user = User::factory()->owner()->unverified()->create();

    $this->actingAs($user)->post('/email/ponow');
    $this->actingAs($user)->post('/email/ponow')->assertStatus(429);

    Queue::assertPushed(SendQueuedNotifications::class, 1);
});

it('wszystkie powiadomienia kont są kolejkowane (ShouldQueue)', function () {
    foreach (glob(app_path('Domain/Accounts/Notifications/*Notification.php')) as $file) {
        $class = 'App\\Domain\\Accounts\\Notifications\\'.basename($file, '.php');
        expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue("{$class} nie jest ShouldQueue");
    }
});

// ---------------------------------------------------------------- wydajność (brak N+1)

it('pulpit i ekran bezpieczeństwa nie ładują leniwie relacji i mają stałą liczbę zapytań niezależnie od liczby użytkowników', function () {
    Model::preventLazyLoading(true);
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);

    // Prawdziwe logowanie (użytkownik pobrany z bazy, role dociągane przez Spatie).
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => TEST_PASSWORD]);
    $few = countQueries(fn () => $this->get('/pulpit')->assertOk());

    User::factory()->count(150)->owner()->create();
    $many = countQueries(fn () => $this->get('/pulpit')->assertOk());
    $security = countQueries(fn () => $this->get('/konto/bezpieczenstwo')->assertOk());

    expect($many)->toBeLessThanOrEqual($few)->and($many)->toBeLessThanOrEqual(6)->and($security)->toBeLessThanOrEqual(6);
});
