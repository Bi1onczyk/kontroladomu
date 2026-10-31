<?php

return [
    // Rodzaje miejscowości (LocalityKind).
    'kinds' => [
        'city' => 'miasto',
        'village' => 'wieś',
        'colony' => 'kolonia',
        'hamlet' => 'przysiółek',
        'settlement' => 'osada',
        'forest_settlement' => 'osada leśna',
        'district' => 'dzielnica',
        'part' => 'część miejscowości',
        'other' => 'miejscowość',
    ],

    // Etykieta miejscowości w podpowiedziach: "Dąbrówka, gm. Dopiewo, pow. poznański, woj. wielkopolskie".
    'label' => ':name, gm. :municipality, pow. :county, woj. :voivodeship',

    'rate_limited' => 'Zbyt wiele zapytań. Spróbuj za chwilę.',

    // Źródło danych (obowiązuje do czasu odpowiedzi GUS w sprawie licencji; patrz docs/GUS_ZAPYTANIE_LICENCJA.md).
    'data_source' => 'GUS, rejestr TERYT',
    'osm_attribution' => '© współtwórcy OpenStreetMap',

    'validation' => [
        'q_format' => 'Pole :attribute może zawierać tylko litery, cyfry, spację oraz znaki - . , \'.',
    ],
];
