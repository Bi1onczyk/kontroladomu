<?php

namespace App\Support\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Konfiguracja zaufanych proxy (Cloudflare) czytana ze zmiennej `TRUSTED_PROXIES`.
 *
 * Zalecane: lista zakresów IP Cloudflare (https://www.cloudflare.com/ips/), NIE "*".
 * Ufamy tylko nagłówkom FOR/PORT/PROTO, nigdy X-Forwarded-Host (nazwa hosta pochodzi z APP_URL, patrz trustHosts).
 */
class TrustedProxyConfig
{
    /**
     * Zamienia wartość zmiennej na argument `trustProxies(at: ...)`.
     *
     * @return array<int, string>|string|null null = brak zaufanych proxy; '*' tylko gdy podano wprost.
     */
    public static function parse(?string $value): array|string|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($value === '*') {
            return '*';
        }

        $list = array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));

        return $list === [] ? null : $list;
    }

    /**
     * Nagłówki, którym ufamy od zaufanego proxy (bez X-Forwarded-Host).
     */
    public static function headers(): int
    {
        return Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;
    }
}
