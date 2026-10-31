<?php

namespace App\Models;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Notifications\ResetPasswordNotification;
use App\Domain\Accounts\Notifications\VerifyEmailNotification;
use App\Domain\Accounts\Policies\UserPolicy;
use App\Domain\Accounts\Support\RecipientMailLimiter;
use App\Domain\Specialists\Models\SpecialistProfile;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Konto użytkownika serwisu (właściciel nieruchomości, fachowiec, pracownik, administrator).
 *
 * Jedno konto może mieć kilka ról. Pola bezpieczeństwa (`banned_at`, `two_factor_*`,
 * `email_verified_at`) nie są w $fillable: ustawia je wyłącznie kod akcji.
 */
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nip',
        'company_name',
        'terms_accepted_at',
        'terms_version',
        'privacy_accepted_at',
        'privacy_version',
        'marketing_consent_at',
        'marketing_consent_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'banned_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_reminder_until' => 'datetime',
        ];
    }

    /**
     * Profil fachowca (istnieje tylko dla kont z rolą `specialist`, po pierwszym wejściu do panelu profilu).
     *
     * @return HasOne<SpecialistProfile, $this>
     */
    public function specialistProfile(): HasOne
    {
        return $this->hasOne(SpecialistProfile::class);
    }

    /**
     * Rola główna konta (decyduje o wyglądzie pulpitu); null, gdy konto nie ma żadnej roli.
     */
    public function primaryRole(): ?UserRole
    {
        $names = $this->getRoleNames();

        foreach (UserRole::priority() as $role) {
            if ($names->contains($role->value)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Czy konto ma przynajmniej jedną rolę wymagającą obowiązkowego 2FA.
     */
    public function requiresTwoFactor(): bool
    {
        foreach (UserRole::cases() as $role) {
            if ($role->requiresTwoFactor() && $this->hasRole($role->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Czy 2FA jest włączone i potwierdzone kodem z aplikacji.
     */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Czy konto jest zablokowane (ban).
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Zużywa kod awaryjny: USUWA go z listy, bez podmiany na nowy (nadpisuje metodę Fortify).
     *
     * Dzięki temu liczba kodów realnie maleje i pulpit może ostrzec, że zostało ich mało.
     * Nazwa metody zostaje z Fortify, bo woła ją jego kontroler wyzwania 2FA.
     *
     * @param  string  $code  Użyty kod awaryjny.
     */
    public function replaceRecoveryCode($code): void
    {
        $remaining = array_values(array_filter(
            (array) $this->recoveryCodes(),
            fn (string $stored): bool => ! hash_equals($stored, (string) $code),
        ));

        $this->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($remaining)),
        ])->save();

        RecoveryCodeReplaced::dispatch($this, $code);
    }

    /**
     * Wysyła (przez kolejkę) e-mail z linkiem potwierdzającym adres.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(RecipientMailLimiter::class)->send($this->email, 'verify', fn () => $this->notify(new VerifyEmailNotification));
    }

    /**
     * Wysyła (przez kolejkę) e-mail z linkiem do ustawienia nowego hasła.
     *
     * @param  string  $token  Token z brokera haseł.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        // Najwyżej 3 maile resetu na godzinę na adres. Po przekroczeniu cicho pomijamy wysyłkę:
        // odpowiedź dla użytkownika jest zawsze ta sama, a napastnik nie może zablokować cudzego resetu.
        app(RecipientMailLimiter::class)->send($this->email, 'reset', fn () => $this->notify(new ResetPasswordNotification($token)));
    }
}
