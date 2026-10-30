<?php

namespace App\Domain\Accounts\Listeners;

use App\Domain\Accounts\Support\TwoFactorLockout;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

/**
 * Reakcje na zdarzenia wyzwania 2FA przy logowaniu: start zegara 10 minut, licznik błędów, zerowanie licznika.
 *
 * Zdarzenie `TwoFactorAuthenticationFailed` Fortify wysyła zarówno dla błędnego kodu z aplikacji,
 * jak i dla błędnego kodu awaryjnego, więc oba rodzaje błędów trafiają do jednego licznika.
 */
class TwoFactorChallengeListeners
{
    /** Czas na wpisanie kodu po poprawnym haśle, w sekundach (10 minut). */
    public const CHALLENGE_SECONDS = 600;

    /** Klucz sesji ze znacznikiem czasu rozpoczęcia wyzwania. */
    public const SESSION_KEY = 'two_factor.challenged_at';

    public function __construct(private readonly TwoFactorLockout $lockout) {}

    /**
     * Po poprawnym haśle zapisuje w sesji moment rozpoczęcia wyzwania.
     */
    public function started(TwoFactorAuthenticationChallenged $event): void
    {
        request()->session()->put(self::SESSION_KEY, now()->getTimestamp());
    }

    /**
     * Zlicza błędny kod (z aplikacji lub awaryjny).
     */
    public function failed(TwoFactorAuthenticationFailed $event): void
    {
        $this->lockout->hit($event->user->getAuthIdentifier());
    }

    /**
     * Po użyciu kodu awaryjnego zapisuje w sesji (flash) liczbę pozostałych kodów dla banera na pulpicie.
     */
    public function recoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        request()->session()->flash('recovery_codes_left', count((array) $event->user->recoveryCodes()));
    }

    /**
     * Zeruje licznik po poprawnym kodzie.
     */
    public function succeeded(ValidTwoFactorAuthenticationCodeProvided $event): void
    {
        $this->lockout->clear($event->user->getAuthIdentifier());
        request()->session()->forget(self::SESSION_KEY);
    }
}
