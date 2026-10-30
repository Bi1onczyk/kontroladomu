<?php

namespace App\Domain\Accounts\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Blokada po 5 błędnych kodach 2FA na 15 minut (ADR-009).
 *
 * Fortify limituje żądania, nie błędy, dlatego liczymy błędy sami. Kluczem jest identyfikator
 * użytkownika (a nie IP), więc zmiana adresu IP nie pomaga w zgadywaniu kodu.
 * Licznik leży w cache (driver `database` na hostingu, `array` w testach).
 */
class TwoFactorLockout
{
    /** Liczba błędnych kodów, po której następuje blokada. */
    public const MAX_ATTEMPTS = 5;

    /** Czas liczenia błędów i trwania blokady w sekundach (15 minut). */
    public const DECAY_SECONDS = 900;

    /**
     * Zapisuje błędny kod dla użytkownika.
     */
    public function hit(int|string $userId): void
    {
        RateLimiter::hit($this->key($userId), self::DECAY_SECONDS);
    }

    /**
     * Zeruje licznik (po poprawnym kodzie).
     */
    public function clear(int|string $userId): void
    {
        RateLimiter::clear($this->key($userId));
    }

    /**
     * Czy użytkownik jest zablokowany.
     */
    public function isLocked(int|string $userId): bool
    {
        return RateLimiter::tooManyAttempts($this->key($userId), self::MAX_ATTEMPTS);
    }

    /**
     * Ile sekund pozostało do końca blokady.
     */
    public function secondsLeft(int|string $userId): int
    {
        return RateLimiter::availableIn($this->key($userId));
    }

    private function key(int|string $userId): string
    {
        return '2fa-fail:'.$userId;
    }
}
