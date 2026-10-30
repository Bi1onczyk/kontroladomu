<?php

use App\Domain\Accounts\Actions\UpdateUserPassword;
use App\Domain\Accounts\Notifications\PasswordChangedNotification;
use App\Domain\Accounts\Notifications\RegisterAttemptNotification;
use App\Domain\Accounts\Notifications\ResetPasswordNotification;
use App\Domain\Accounts\Notifications\TwoFactorResetNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;

const GOOD_PASSWORD = 'zielony-dach-w-lutym';

it('loguje poprawnymi danymi i zapisuje wpis w dzienniku', function () {
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD]);

    $this->post('/logowanie', ['email' => 'Jan@Example.pl', 'password' => GOOD_PASSWORD])
        ->assertRedirect('/pulpit');

    $this->assertAuthenticatedAs($user);
    expect(session('auth.password_confirmed_at'))->not->toBeNull();

    $entry = Activity::where('event', 'login')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($user->id)
        ->and($entry->properties->has('ip'))->toBeTrue()
        ->and(json_encode($entry->properties))->not->toContain(GOOD_PASSWORD);
});

it('pokazuje ten sam błąd dla złego hasła i nieistniejącego konta', function () {
    User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD]);

    $a = $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo']);
    $b = $this->post('/logowanie', ['email' => 'brak@example.pl', 'password' => 'zle-haslo-zle-haslo']);

    $a->assertSessionHasErrors(['email' => __('auth.failed')]);
    $b->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

it('zbanowane konto ujawnia blokadę dopiero po poprawnym haśle', function () {
    User::factory()->owner()->banned()->create(['email' => 'ban@example.pl', 'password' => GOOD_PASSWORD]);

    $this->post('/logowanie', ['email' => 'ban@example.pl', 'password' => 'zle-haslo-zle-haslo'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->post('/logowanie', ['email' => 'ban@example.pl', 'password' => GOOD_PASSWORD])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe(__('auth.login.blocked', ['email' => config('app.contact_email')]));
    $this->assertGuest();
});

it('blokuje logowanie po 5 próbach na minutę', function () {
    User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD]);

    foreach (range(1, 5) as $i) {
        $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo']);
    }

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD])->assertStatus(429);
});

it('wymaga Turnstile na logowaniu dopiero po 3 nieudanych próbach', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'sekret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);
    User::factory()->owner()->create(['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD]);

    foreach (range(1, 3) as $i) {
        $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zle-haslo-zle-haslo'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    Http::assertNothingSent();

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD])
        ->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => GOOD_PASSWORD, 'cf-turnstile-response' => 'ok'])
        ->assertRedirect('/pulpit');
});

it('wylogowuje i zapisuje wpis w dzienniku', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->post('/wyloguj')->assertRedirect();

    $this->assertGuest();
    expect(Activity::where('event', 'logout')->count())->toBe(1);
});

it('odpowiada tak samo na reset hasła dla istniejącego i nieistniejącego konta', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl']);

    $a = $this->from('/nie-pamietam-hasla')->post('/nie-pamietam-hasla', ['email' => 'jan@example.pl']);
    $b = $this->from('/nie-pamietam-hasla')->post('/nie-pamietam-hasla', ['email' => 'brak@example.pl']);
    // trzecia prośba trafia w 60-sekundowy odstęp brokera - odpowiedź nadal ta sama
    $c = $this->from('/nie-pamietam-hasla')->post('/nie-pamietam-hasla', ['email' => 'jan@example.pl']);

    foreach ([$a, $b, $c] as $response) {
        $response->assertRedirect('/nie-pamietam-hasla')
            ->assertSessionHas('status', __('passwords.sent'))
            ->assertSessionHasNoErrors();
    }

    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
});

it('resetuje hasło przez link, kończy sesje i zapisuje wpis w dzienniku', function () {
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl']);
    $token = Password::createToken($user);
    DB::table('sessions')->insert([
        'id' => 'cudza-sesja', 'user_id' => $user->id, 'ip_address' => '1.1.1.1',
        'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
    ]);
    config(['session.driver' => 'database']);

    $this->post('/nowe-haslo', [
        'token' => $token, 'email' => 'jan@example.pl', 'password' => 'nowe-zielone-haslo-123',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nowe-zielone-haslo-123', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(Activity::where('event', 'password_reset')->count())->toBe(1);
});

it('odrzuca zbyt słabe hasło przy resecie', function () {
    $user = User::factory()->owner()->create(['email' => 'jan@example.pl']);

    $this->post('/nowe-haslo', [
        'token' => Password::createToken($user), 'email' => 'jan@example.pl', 'password' => 'krotkie',
    ])->assertSessionHasErrors('password');
});

it('zmienia hasło zalogowanego, zachowuje bieżącą sesję i wysyła powiadomienie', function () {
    Notification::fake();
    config(['session.driver' => 'database']);
    $user = User::factory()->owner()->create(['password' => GOOD_PASSWORD]);

    foreach (['biezaca-sesja', 'inna-sesja'] as $id) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '1.1.1.1',
            'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
        ]);
    }

    app(UpdateUserPassword::class)->handle($user, 'zupelnie-nowe-haslo-42', 'biezaca-sesja');

    expect(Hash::check('zupelnie-nowe-haslo-42', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'inna-sesja')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'biezaca-sesja')->exists())->toBeTrue();
    Notification::assertSentTo($user, PasswordChangedNotification::class);
    expect(Activity::where('event', 'password_changed')->count())->toBe(1);
});

it('zmienia hasło przez formularz i wraca na ekran bezpieczeństwa z komunikatem', function () {
    Notification::fake();
    $user = User::factory()->owner()->create(['password' => GOOD_PASSWORD]);

    $this->actingAs($user)->post('/konto/haslo', [
        'current_password' => GOOD_PASSWORD, 'password' => 'zupelnie-nowe-haslo-42',
    ])->assertRedirect(route('account.security'))->assertSessionHas('status', __('auth.security.success'));

    $this->assertAuthenticatedAs($user->fresh());
});

it('wymaga obecnego hasła i innego nowego hasła przy zmianie', function () {
    $user = User::factory()->owner()->create(['password' => GOOD_PASSWORD]);

    $this->actingAs($user)->post('/konto/haslo', ['current_password' => 'zle', 'password' => 'zupelnie-nowe-haslo-42'])
        ->assertSessionHasErrors('current_password');
    $this->actingAs($user)->post('/konto/haslo', ['current_password' => GOOD_PASSWORD, 'password' => GOOD_PASSWORD])
        ->assertSessionHasErrors('password');
});

it('buduje wiadomości z kluczami z lang/pl (bez surowych kluczy)', function () {
    $user = User::factory()->owner()->create();

    $mails = [
        (new PasswordChangedNotification)->toMail($user),
        (new ResetPasswordNotification('token'))->toMail($user),
        (new RegisterAttemptNotification)->toMail($user),
        (new TwoFactorResetNotification(true))->toMail($user),
    ];

    foreach ($mails as $mail) {
        $html = (string) $mail->render();
        expect($html)->not->toContain('mail.')->and($mail->subject)->not->toContain('mail.');
    }
});
