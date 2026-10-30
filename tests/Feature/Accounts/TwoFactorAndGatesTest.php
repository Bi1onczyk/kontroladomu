<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

function validTotp(User $user): string
{
    $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);

    return app(Google2FA::class)->getCurrentOtp($secret);
}

it('kieruje niepotwierdzony e-mail na ekran potwierdzenia', function () {
    $user = User::factory()->owner()->unverified()->create();

    $this->actingAs($user)->get('/pulpit')->assertRedirect(route('verification.notice'));
});

it('wpuszcza właściciela na pulpit bez 2FA', function () {
    $this->actingAs(User::factory()->owner()->create())->get('/pulpit')->assertOk();
});

it('wpuszcza na pulpit odpowiedni widok dla roli głównej', function () {
    $this->actingAs(User::factory()->owner()->specialist()->withTwoFactor()->create())
        ->get('/pulpit')->assertOk()->assertViewIs('dashboard.specialist');
});

it('nie wpuszcza fachowca ani admina bez potwierdzonego 2FA na pulpit', function () {
    foreach (['specialist', 'admin'] as $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get('/pulpit')
            ->assertRedirect(route('account.security'))
            ->assertSessionHas('two_factor_required', true);
    }
});

it('odpowiada 403 na żądanie JSON zamiast przekierowania', function () {
    $this->actingAs(User::factory()->specialist()->create())->getJson('/pulpit')->assertForbidden();
});

it('wpuszcza fachowca z potwierdzonym 2FA', function () {
    $this->actingAs(User::factory()->specialist()->withTwoFactor()->create())->get('/pulpit')->assertOk();
});

it('pozwala fachowcowi bez 2FA wejść na ekran bezpieczeństwa (poza trzecią bramką)', function () {
    $this->actingAs(User::factory()->specialist()->create())
        ->get('/konto/bezpieczenstwo')
        ->assertOk()
        ->assertViewHas('step', 'intro')
        ->assertViewHas('forced', true);
});

it('przechodzi kreator 2FA: włączenie, QR, potwierdzenie kodem, kody awaryjne', function () {
    $user = User::factory()->specialist()->create();
    $confirmed = ['auth.password_confirmed_at' => time()];

    $this->actingAs($user)->withSession($confirmed)->post('/konto/bezpieczenstwo/2fa')
        ->assertRedirect(route('account.security'));

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasConfirmedTwoFactor())->toBeFalse()
        ->and($user->recoveryCodes())->toHaveCount(8)
        ->and($user->recoveryCodes()[0])->toMatch('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/');

    $this->actingAs($user)->get('/konto/bezpieczenstwo')
        ->assertViewHas('step', 'scan')
        ->assertViewHas('qrDataUri', fn ($uri) => str_starts_with($uri, 'data:image/svg+xml;base64,'))
        ->assertViewHas('secretKey');

    $this->actingAs($user)->withSession($confirmed)
        ->post('/konto/bezpieczenstwo/2fa/potwierdz', ['code' => '000000'])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->hasConfirmedTwoFactor())->toBeFalse();

    $this->actingAs($user)->withSession($confirmed)
        ->post('/konto/bezpieczenstwo/2fa/potwierdz', ['code' => validTotp($user)])
        ->assertRedirect(route('account.security'));
    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue();

    $this->actingAs($user)->get('/konto/bezpieczenstwo')
        ->assertViewHas('step', 'codes')
        ->assertViewHas('recoveryCodes', fn ($codes) => count($codes) === 8);

    expect(Activity::whereIn('event', ['two_factor_enabled', 'two_factor_confirmed'])->count())->toBe(2);
});

it('wymaga świeżego potwierdzenia hasła przed włączeniem 2FA', function () {
    $this->actingAs(User::factory()->specialist()->create())
        ->post('/konto/bezpieczenstwo/2fa')
        ->assertRedirect(route('password.confirm'));
});

it('nie pozwala wyłączyć 2FA fachowcowi i adminowi, ale pozwala właścicielowi', function () {
    $confirmed = ['auth.password_confirmed_at' => time()];

    $specialist = User::factory()->specialist()->withTwoFactor()->create();
    $this->actingAs($specialist)->withSession($confirmed)->delete('/konto/bezpieczenstwo/2fa')->assertForbidden();
    expect($specialist->fresh()->hasConfirmedTwoFactor())->toBeTrue();

    $owner = User::factory()->owner()->withTwoFactor()->create();
    $this->actingAs($owner)->withSession($confirmed)->delete('/konto/bezpieczenstwo/2fa')->assertRedirect();
    expect($owner->fresh()->hasConfirmedTwoFactor())->toBeFalse()
        ->and(Activity::where('event', 'two_factor_disabled')->count())->toBe(1);
});

it('pobiera kody awaryjne jako plik bez cache i generuje nowe', function () {
    $confirmed = ['auth.password_confirmed_at' => time()];
    $user = User::factory()->specialist()->withTwoFactor()->create();
    $old = $user->recoveryCodes();

    $response = $this->actingAs($user)->withSession($confirmed)->get('/konto/bezpieczenstwo/kody-awaryjne.txt');
    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->toContain($old[0]);

    $this->actingAs($user)->withSession($confirmed)->post('/konto/bezpieczenstwo/kody-awaryjne')
        ->assertRedirect(route('account.security'));
    expect($user->fresh()->recoveryCodes())->not->toBe($old);
});

it('odkłada przypomnienie o 2FA o 30 dni', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->post('/konto/przypomnienie-2fa')->assertRedirect();

    expect($user->fresh()->two_factor_reminder_until->isAfter(now()->addDays(29)))->toBeTrue();
});

it('przekierowuje na wyzwanie 2FA po poprawnym haśle i loguje poprawnym kodem', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);
    // Sekret znany testowi (z fabryki), kod z aplikacji liczymy z niego.
    $code = app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');

    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym'])
        ->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    $this->post('/logowanie/kod', ['code' => $code])->assertRedirect('/pulpit');
    $this->assertAuthenticatedAs($user);
});

it('przyjmuje kod awaryjny tylko raz', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);

    $login = fn () => $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);

    $login();
    $this->post('/logowanie/kod', ['recovery_code' => 'aaaaaaaaaa-bbbbbbbbbb'])->assertRedirect('/pulpit');
    expect($user->fresh()->recoveryCodes())->not->toContain('aaaaaaaaaa-bbbbbbbbbb');

    auth()->logout();
    $login();
    $this->post('/logowanie/kod', ['recovery_code' => 'aaaaaaaaaa-bbbbbbbbbb'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('blokuje na 15 minut po 5 błędnych kodach (z aplikacji i awaryjnych)', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);

    foreach ([['code' => '111111'], ['code' => '222222'], ['recovery_code' => 'zly-kod'], ['code' => '333333'], ['recovery_code' => 'inny-zly']] as $payload) {
        $this->post('/logowanie/kod', $payload)->assertSessionHasErrors();
    }

    $locked = $this->post('/logowanie/kod', ['code' => app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP')]);
    $locked->assertStatus(429)->assertHeader('Retry-After');
    expect((int) $locked->headers->get('Retry-After'))->toBeGreaterThan(800)->toBeLessThanOrEqual(900);
    $this->assertGuest();

    // Blokada obowiązuje użytkownika, nie sesję: nowe logowanie hasłem jej nie zdejmuje.
    auth()->logout();
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);
    $this->get('/logowanie/kod')->assertStatus(429);

    $this->travel(16)->minutes();
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);
    $this->get('/logowanie/kod')->assertOk();
});

it('kończy wyzwanie 2FA po 10 minutach', function () {
    User::factory()->specialist()->withTwoFactor()->create(['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);
    $this->post('/logowanie', ['email' => 'jan@example.pl', 'password' => 'zielony-dach-w-lutym']);

    $this->get('/logowanie/kod')->assertOk();

    $this->travel(11)->minutes();
    $this->get('/logowanie/kod')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('auth.twofa.challenge_expired'));
    $this->assertGuest();
});

it('wylogowuje sesję konta z wymogiem 2FA odtworzoną z ciasteczka "zapamiętaj mnie"', function () {
    $user = User::factory()->specialist()->withTwoFactor()->create();
    $guard = auth()->guard('web');
    $guard->login($user, true);

    // Symulacja: guard zgłasza, że użytkownik został odtworzony z ciasteczka recaller.
    $property = new ReflectionProperty($guard, 'viaRemember');
    $property->setValue($guard, true);

    $this->get('/pulpit')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('zapisuje użycie bezpiecznych zdarzeń bez sekretów w dzienniku', function () {
    Event::fake([]);
    $user = User::factory()->specialist()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/konto/bezpieczenstwo/2fa');

    $json = Activity::all()->map(fn ($a) => json_encode($a->properties))->implode('');
    expect($json)->not->toContain((string) $user->fresh()->two_factor_secret);
});
