<?php

namespace App\Domain\Accounts\Support;

use App\Support\EmailNormalizer;
use Illuminate\Support\Facades\Cache;

/**
 * Liczniki nieudanych logowań w cache (15 minut): osobno dla adresu IP i dla konta (adresu e-mail).
 *
 * Po {@see self::THRESHOLD} nieudanych próbach z jednego IP albo po {@see self::ACCOUNT_THRESHOLD}
 * nieudanych próbach na jedno konto (z dowolnych adresów IP) formularz logowania wymaga Turnstile.
 * Licznik konta zamiast blokady 429 chroni przed zgadywaniem hasła z wielu adresów IP, a nie pozwala
 * napastnikowi zablokować cudzego konta. Adres e-mail trafia do cache wyłącznie jako skrót SHA-1 postaci kanonicznej (bez akcentów i wielkości liter,
 * zgodnie z collation bazy), więc wariantami zapisu adresu nie da się obejść licznika.
 */
class LoginFailureCounter
{
    /** Liczba nieudanych prób z jednego IP, po której wymagamy Turnstile. */
    public const THRESHOLD = 3;

    /** Liczba nieudanych prób na jedno konto (z dowolnych IP), po której wymagamy Turnstile. */
    public const ACCOUNT_THRESHOLD = 5;

    private const TTL_SECONDS = 900;

    /**
     * Zwiększa liczniki dla adresu IP i (jeśli podano) dla konta.
     */
    public function hit(string $ip, ?string $email = null): void
    {
        $this->increment($this->ipKey($ip));

        if ($email !== null && $email !== '') {
            $this->increment($this->accountKey($email));
        }
    }

    /**
     * Zeruje licznik konta (po udanym logowaniu osoby, która zna hasło).
     * Licznik IP celowo NIE jest zerowany: napastnik mógłby co drugą próbę logować się na własne konto.
     */
    public function clearAccount(string $email): void
    {
        Cache::forget($this->accountKey($email));
    }

    /**
     * Liczba nieudanych prób z adresu IP w ostatnich 15 minutach.
     */
    public function count(string $ip): int
    {
        return (int) Cache::get($this->ipKey($ip), 0);
    }

    /**
     * Liczba nieudanych prób na konto w ostatnich 15 minutach.
     */
    public function accountCount(string $email): int
    {
        return (int) Cache::get($this->accountKey($email), 0);
    }

    /**
     * Czy trzeba pokazać i sprawdzić Turnstile (przekroczony próg dla IP albo dla konta).
     */
    public function requiresTurnstile(string $ip, ?string $email = null): bool
    {
        if ($this->count($ip) >= self::THRESHOLD) {
            return true;
        }

        return $email !== null && $email !== '' && $this->accountCount($email) >= self::ACCOUNT_THRESHOLD;
    }

    private function increment(string $key): void
    {
        Cache::add($key, 0, self::TTL_SECONDS);
        Cache::increment($key);
    }

    private function ipKey(string $ip): string
    {
        return 'login-failures:'.$ip;
    }

    private function accountKey(string $email): string
    {
        return 'login-failures-account:'.sha1(EmailNormalizer::canonical($email));
    }
}
