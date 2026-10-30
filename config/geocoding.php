<?php

/*
| Geokodowanie adresów (ADR-013). Domyślny dostawca: publiczny Nominatim (OpenStreetMap).
| Polityka OSMF (sprawdzona 30.10.2026): max 1 zapytanie/s dla całej aplikacji, własny User-Agent z kontaktem,
| zakaz autouzupełniania na publicznym API. Dane na licencji ODbL: przy mapie "© współtwórcy OpenStreetMap".
*/
return [
    'provider' => 'nominatim',

    // Zmiana na własny serwer Nominatim nie wymaga zmian w kodzie.
    'base_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),

    // Adres kontaktowy do nagłówka User-Agent (wymóg polityki Nominatim). Pusty = serwis NIE wysyła zapytań
    // do publicznego Nominatim (wyniki z cache działają dalej). Dla własnego serwera (inny host) kontakt nie jest wymagany.
    'contact' => env('NOMINATIM_CONTACT_EMAIL'),

    'user_agent_name' => 'kontroladomu.pl',
    'user_agent_version' => '0.1',
    'user_agent_site' => 'https://kontroladomu.pl',

    'timeout' => 5,
    'connect_timeout' => 3,

    // Ile dni pamiętamy wynik: trafienie / "nie znaleziono". Błędy sieci nie są zapisywane.
    'ttl_found_days' => 180,
    'ttl_not_found_days' => 7,

    // Odstęp między zapytaniami (limit 1/s + zapas) i blokada współdzielona przez procesy PHP.
    'min_interval_ms' => 1100,
    'lock_seconds' => 10,
    'lock_wait_seconds' => 5,

    // Przerwa w wysyłaniu zapytań po awarii (timeout, 5xx) i po odmowie (429/403).
    'cooldown_seconds' => 300,
    'blocked_cooldown_seconds' => 1800,

    // Prostokąt Polski: współrzędne spoza niego uznajemy za błąd odpowiedzi.
    'bounds' => [
        'lat_min' => 48.9, 'lat_max' => 54.9,
        'lng_min' => 14.0, 'lng_max' => 24.2,
    ],
];
