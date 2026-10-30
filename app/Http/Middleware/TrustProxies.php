<?php

namespace App\Http\Middleware;

use App\Support\Security\TrustedProxyConfig;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;

/**
 * Zaufane proxy (zakresy IP Cloudflare) czytane z konfiguracji `security.trusted_proxies`.
 *
 * Konfigurację czytamy dopiero w czasie żądania, gdy plik `.env` jest już wczytany. Odczyt funkcji env
 * w `bootstrap/app.php` wykonywał się za wcześnie i ignorował wartość z `.env` (jedyne miejsce
 * z odczytem zmiennej TRUSTED_PROXIES to config/security.php).
 * Ufamy wyłącznie nagłówkom X-Forwarded-For/-Port/-Proto, nigdy X-Forwarded-Host.
 */
class TrustProxies extends BaseTrustProxies
{
    /**
     * Zaufane adresy proxy: z konfiguracji, a w razie braku wartość ustawiona programowo (`TrustProxies::at()`).
     *
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        return TrustedProxyConfig::parse(config('security.trusted_proxies')) ?? parent::proxies();
    }

    /**
     * Nagłówki, którym ufamy od zaufanego proxy (bez X-Forwarded-Host).
     *
     * @return int
     */
    protected function headers()
    {
        return TrustedProxyConfig::headers();
    }
}
