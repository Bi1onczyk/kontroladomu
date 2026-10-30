<?php

namespace App\Services\Turnstile;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Weryfikuje token Cloudflare Turnstile po stronie serwera (bez zewnętrznej paczki, ADR-008).
 *
 * Zasada "fail closed": brak tokenu, błąd sieci lub odpowiedź inna niż sukces = odmowa.
 */
class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Czy weryfikacja jest włączona (wyłączana tylko lokalnie i w testach).
     */
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled');
    }

    /**
     * Sprawdza token z formularza w Cloudflare.
     *
     * @param  string|null  $token  Wartość pola `cf-turnstile-response`.
     * @param  string|null  $ip  Adres IP klienta (opcjonalny dla Cloudflare, poprawia ocenę).
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if ($token === null || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::ENDPOINT, array_filter([
                    'secret' => (string) config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            Log::warning('Turnstile: błąd połączenia z Cloudflare.', ['exception' => $e::class]);

            return false;
        }

        if ($response->status() !== 200) {
            Log::warning('Turnstile: nieoczekiwany status odpowiedzi.', ['status' => $response->status()]);

            return false;
        }

        return $response->json('success') === true;
    }
}
