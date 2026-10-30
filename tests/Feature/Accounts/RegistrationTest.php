<?php

use App\Domain\Accounts\Notifications\RegisterAttemptNotification;
use App\Domain\Accounts\Notifications\VerifyEmailNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('rejestruje właściciela, nie loguje i wysyła link weryfikacyjny', function () {
    Notification::fake();

    $this->post('/rejestracja/wlasciciel', ownerPayload())
        ->assertRedirect(route('register.sent'))
        ->assertSessionHas('registered_email', 'anna@example.pl');

    $user = User::where('email', 'anna@example.pl')->firstOrFail();

    expect($user->hasRole('owner'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->nip)->toBeNull()
        ->and($user->terms_accepted_at)->not->toBeNull()
        ->and($user->terms_version)->toBe(config('legal.terms_version'))
        ->and($user->privacy_accepted_at)->not->toBeNull()
        ->and($user->marketing_consent_at)->toBeNull();

    $this->assertGuest();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('zapisuje zgodę marketingową tylko gdy zaznaczona', function () {
    Notification::fake();

    $this->post('/rejestracja/wlasciciel', ownerPayload(['marketing' => '1']));

    $user = User::where('email', 'anna@example.pl')->firstOrFail();
    expect($user->marketing_consent_at)->not->toBeNull()
        ->and($user->marketing_consent_version)->toBe(config('legal.marketing_version'));
});

it('rejestruje fachowca z NIP-em znormalizowanym do cyfr', function () {
    Notification::fake();

    $this->post('/rejestracja/fachowiec', specialistPayload())
        ->assertRedirect(route('register.sent'));

    $user = User::where('email', 'anna@example.pl')->firstOrFail();
    expect($user->hasRole('specialist'))->toBeTrue()
        ->and($user->nip)->toBe('6931171824')
        ->and($user->company_name)->toBe('Kominiarz Jan');
});

it('odrzuca fachowca z błędnym NIP, bez oświadczenia i bez regulaminu', function () {
    $this->post('/rejestracja/fachowiec', specialistPayload(['nip' => '6931171825']))
        ->assertSessionHasErrors('nip');
    $this->post('/rejestracja/fachowiec', specialistPayload(['business_declaration' => null]))
        ->assertSessionHasErrors('business_declaration');
    $this->post('/rejestracja/wlasciciel', ownerPayload(['terms' => null]))
        ->assertSessionHasErrors('terms');

    expect(User::count())->toBe(0);
});

it('odrzuca zbyt krótkie hasło', function () {
    $this->post('/rejestracja/wlasciciel', ownerPayload(['password' => 'krotkie']))
        ->assertSessionHasErrors('password');
});

it('nie blokuje rejestracji przy zajętym NIP', function () {
    Notification::fake();
    User::factory()->specialist()->create(['email' => 'inny@example.pl']);

    $this->post('/rejestracja/fachowiec', specialistPayload())
        ->assertRedirect(route('register.sent'))
        ->assertSessionHasNoErrors();

    expect(User::where('nip', '6931171824')->count())->toBe(2);
});

it('przy zajętym e-mailu nie tworzy konta i pokazuje ten sam ekran', function () {
    Notification::fake();
    $existing = User::factory()->owner()->create(['email' => 'anna@example.pl']);

    $this->post('/rejestracja/wlasciciel', ownerPayload())
        ->assertRedirect(route('register.sent'))
        ->assertSessionHasNoErrors();

    expect(User::count())->toBe(1);
    Notification::assertSentTo($existing, RegisterAttemptNotification::class);
    Notification::assertNotSentTo($existing, VerifyEmailNotification::class);
});

it('wysyła wiadomość o próbie rejestracji najwyżej raz na godzinę', function () {
    Notification::fake();
    $existing = User::factory()->owner()->create(['email' => 'anna@example.pl']);

    $this->post('/rejestracja/wlasciciel', ownerPayload());
    $this->post('/rejestracja/wlasciciel', ownerPayload());

    Notification::assertSentToTimes($existing, RegisterAttemptNotification::class, 1);
});

it('ogranicza rejestracje z jednego adresu IP do 10 na godzinę (limit po e-mailu usunięty, audyt S2)', function () {
    Notification::fake();

    foreach (range(1, 10) as $i) {
        $this->post('/rejestracja/wlasciciel', ownerPayload())->assertRedirect(route('register.sent'));
    }

    $this->post('/rejestracja/wlasciciel', ownerPayload())->assertStatus(429);
});

it('wymaga Turnstile na rejestracji, gdy jest włączony', function () {
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'sekret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false], 200)]);

    $this->post('/rejestracja/wlasciciel', ownerPayload(['cf-turnstile-response' => 'zly']))
        ->assertSessionHasErrors('cf-turnstile-response');
    $this->post('/rejestracja/wlasciciel', ownerPayload())
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(User::count())->toBe(0);
});

it('przepuszcza rejestrację z poprawnym tokenem Turnstile', function () {
    Notification::fake();
    config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'sekret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    $this->post('/rejestracja/wlasciciel', ownerPayload(['cf-turnstile-response' => 'dobry']))
        ->assertRedirect(route('register.sent'));

    Http::assertSent(fn ($request) => $request['response'] === 'dobry' && $request['secret'] === 'sekret');
});

it('pokazuje ekrany rejestracji gościom', function () {

    $this->get('/rejestracja')->assertOk();
    $this->get('/rejestracja/wlasciciel')->assertOk();
    $this->get('/rejestracja/fachowiec')->assertOk();
    $this->get('/rejestracja/sprawdz-skrzynke')->assertOk();
});
