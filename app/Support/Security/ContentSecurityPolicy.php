<?php

namespace App\Support\Security;

/**
 * Buduje nagłówek Content-Security-Policy z nonce generowanym na każde żądanie.
 *
 * Zasady dla widoków: zero atrybutów `style=""`, zero `<script>` bez `nonce`, zero `onclick=""`.
 * Alpine działa w wersji CSP (bez `eval`), dlatego nie ma `'unsafe-eval'`.
 */
class ContentSecurityPolicy
{
    private const TURNSTILE = 'https://challenges.cloudflare.com';

    /**
     * @param  string  $nonce  Jednorazowa wartość dla bieżącego żądania.
     * @param  bool  $local  Czy środowisko lokalne (dopuszcza serwer Vite).
     * @param  bool  $production  Czy produkcja (dodaje upgrade-insecure-requests).
     * @param  list<string>  $viteOrigins  Adresy serwera Vite.
     */
    public function __construct(
        private readonly string $nonce,
        private readonly bool $local = false,
        private readonly bool $production = false,
        private readonly array $viteOrigins = [],
    ) {}

    /**
     * Zwraca pełną wartość nagłówka.
     */
    public function build(): string
    {
        $vite = $this->local ? $this->viteOrigins : [];
        $viteWs = $this->local ? ['ws://127.0.0.1:5173', 'ws://localhost:5173'] : [];

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$this->nonce}'", self::TURNSTILE, ...$vite],
            // Vite w trybie dev wstrzykuje style przez JS, więc lokalnie dopuszczamy 'unsafe-inline' dla stylów.
            'style-src' => ["'self'", ...($this->local ? ["'unsafe-inline'"] : [])],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'", ...$vite],
            'connect-src' => ["'self'", self::TURNSTILE, ...$vite, ...$viteWs],
            'frame-src' => [self::TURNSTILE],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $parts = [];
        foreach ($directives as $name => $sources) {
            $parts[] = $name.' '.implode(' ', $sources);
        }

        if ($this->production) {
            $parts[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $parts);
    }
}
