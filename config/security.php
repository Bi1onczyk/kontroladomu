<?php

/*
| Nagłówki bezpieczeństwa (ADR-007). Zbudowane w App\Http\Middleware\SecurityHeaders.
*/
return [
    'headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        // geolokalizacja potrzebna później do "Użyj mojej lokalizacji"
        'permissions_policy' => 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)',
        'cross_origin_opener_policy' => 'same-origin',
        // bez "preload" i "includeSubDomains" na starcie; tylko https + production
        'hsts' => 'max-age=31536000',
    ],

    // Zakresy IP zaufanych proxy (Cloudflare) po przecinku; czytane też w bootstrap/app.php i przez ProductionConfigGuard.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        // Tryb "report only" przydaje się przy dopisywaniu nowych domen (mapy, kalendarz).
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
        // Adresy serwera Vite; dopisywane do CSP wyłącznie w APP_ENV=local.
        'vite_dev_origins' => [
            'http://127.0.0.1:5173',
            'http://localhost:5173',
        ],
    ],
];
