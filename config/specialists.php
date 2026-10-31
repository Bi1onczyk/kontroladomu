<?php

/*
| Ustawienia profilu fachowca (zadanie 004, ADR-017...ADR-021).
| Wartości biznesowe i prawne są tu, a nie w kodzie, żeby zmiana (np. okresu archiwum po opinii prawnika)
| nie wymagała migracji ani zmiany klas.
*/
return [
    // Adresy zajęte pod przyszłe trasy /fachowiec/{slug}; slug nie może ich przyjąć (ADR-018).
    'reserved_slugs' => [
        'nowy', 'nowa', 'szukaj', 'panel', 'admin', 'api', 'zdjecie', 'kontakt', 'fachowiec', 'fachowcy',
        'profil', 'login', 'logowanie', 'rejestracja', 'regulamin', 'pomoc', 'edytuj', 'new', 'search',
    ],

    'slug_min_length' => 3,
    'slug_max_length' => 60,

    // Po pierwszej publikacji adres można zmienić najwyżej raz na tyle dni.
    'slug_change_interval_days' => 30,

    'description_max_length' => 1500,
    'display_name_max_length' => 120,

    // Wymóg prywatnego telefonu do publikacji (S10, do potwierdzenia przez właściciela; false = telefon niewymagany).
    'phone_required_to_publish' => true,

    // Promień dojazdu (km) i stawka za kilometr (grosze).
    'radius_min_km' => 5,
    'radius_max_km' => 150,
    'radius_default_km' => 25,
    'travel_rate_max_grosze' => 5000,

    // Cena przeglądu w groszach: 1 zł - 50 000 zł (mniej niż 1 zł traktujemy jako literówkę).
    'price_min_grosze' => 100,
    'price_max_grosze' => 5_000_000,

    // Zaświadczenia (ADR-019).
    'certificate_max_kb' => 5120,
    'certificate_max_count' => 10,
    'certificate_max_total_kb' => 40960,
    'image_max_megapixels' => 16,
    'image_max_side_px' => 2400,
    'image_jpeg_quality' => 85,

    // Archiwum skanów po usunięciu konta (ADR-021). Okres do weryfikacji przez prawnika; zmiana = jedna wartość.
    'archive_retention_months' => 12,

    // Tylko zapisane na potrzeby zadania 005 (usunięcie plików odrzuconej weryfikacji); w 004 bez implementacji.
    'rejected_certificates_retention_days' => 30,

    // Ile razy na dobę (okno 24 h) konto może zmienić miejscowość startową (ochrona Nominatim, audyt 004 #3).
    'locality_changes_per_day' => 5,

    // Ile miejscowości komenda ponowień geokodowania wrzuca do kolejki na jedno uruchomienie.
    'geocoding_retry_batch' => 20,
];
