<?php

use App\Domain\Accounts\Notifications\VerifyEmailNotification;
use App\Domain\Accounts\Notifications\VerifyPendingEmailNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user, ?string $hash = null, int $minutes = 1440): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('potwierdza adres z podpisanego linku bez logowania', function () {
    $user = User::factory()->specialist()->unverified()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect(route('verification.done'))
        ->assertSessionHas('verify_status', 'verified')
        ->assertSessionHas('verified_role', 'specialist');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertGuest();
});

it('odrzuca link ze złym skrótem lub po terminie', function () {
    $user = User::factory()->owner()->unverified()->create();

    $this->get(verificationUrl($user, 'zly-hash'))
        ->assertRedirect(route('verification.done'))
        ->assertSessionHas('verify_status', 'invalid');
    $this->get(verificationUrl($user, null, -5))->assertSessionHas('verify_status', 'invalid');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('odrzuca link bez podpisu', function () {
    $user = User::factory()->owner()->unverified()->create();

    $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
        ->assertSessionHas('verify_status', 'invalid');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('informuje, że adres jest już potwierdzony', function () {
    $user = User::factory()->owner()->create();

    $this->get(verificationUrl($user))->assertSessionHas('verify_status', 'already');
});

it('stary link przestaje działać po zmianie adresu', function () {
    Notification::fake();
    $user = User::factory()->owner()->unverified()->create(['email' => 'stary@example.pl']);
    $oldLink = verificationUrl($user);

    $this->actingAs($user)->post('/email/zmien', ['email' => 'Nowy@Example.pl'])
        ->assertRedirect(route('verification.notice'));

    // R1: adres konta zmienia się dopiero po kliknięciu w link wysłany na NOWY adres.
    expect($user->fresh()->email)->toBe('stary@example.pl')
        ->and($user->fresh()->pending_email)->toBe('nowy@example.pl');
    Notification::assertSentOnDemand(VerifyPendingEmailNotification::class);

    auth()->logout();
    $this->get($oldLink)->assertSessionHas('verify_status', 'invalid');
});

// Zmiana na zajęty adres nie może ujawniać konta (audyt S1): odpowiedź jest taka sama jak dla wolnego adresu.
// Szczegółowe testy: AuditFixesTest.
it('nie pozwala zmienić adresu na obecny, a zajęty adres traktuje neutralnie (bez błędu)', function () {
    User::factory()->owner()->create(['email' => 'zajety@example.pl']);
    $user = User::factory()->owner()->unverified()->create(['email' => 'moj@example.pl']);

    $this->actingAs($user)->post('/email/zmien', ['email' => 'zajety@example.pl'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/email/zmien', ['email' => 'moj@example.pl'])->assertSessionHasErrors('email');
});

it('nie pozwala zmienić adresu po potwierdzeniu', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->get('/email/zmien')->assertForbidden();
    $this->actingAs($user)->post('/email/zmien', ['email' => 'nowy@example.pl'])->assertForbidden();
});

it('wysyła link ponownie zalogowanemu niepotwierdzonemu, z limitem 1 na minutę', function () {
    Notification::fake();
    $user = User::factory()->owner()->unverified()->create();

    $this->actingAs($user)->post('/email/ponow')->assertSessionHas('status');
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

    $this->actingAs($user)->post('/email/ponow')->assertStatus(429);
});

it('buduje wiadomość z linkiem ważnym 24 godziny', function () {
    $user = User::factory()->owner()->unverified()->create();

    $mail = (new VerifyEmailNotification)->toMail($user);
    $url = $mail->actionUrl;

    expect($url)->toContain('/email/potwierdz/'.$user->id.'/'.sha1($user->email))
        ->and($url)->toContain('signature=')
        ->and($mail->subject)->toBe(__('mail.verify_subject'));

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect((int) $query['expires'])->toBeGreaterThan(now()->addMinutes(1439)->getTimestamp());
});
