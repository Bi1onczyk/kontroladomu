@props(['key', 'param' => 'email', 'value'])

@php
    // Wstawia pogrubioną wartość w miejsce parametru z pliku lang bez użycia {!! !!}:
    // tłumaczymy z unikalnym znacznikiem, rozcinamy tekst i składamy ponownie z <strong>.
    $marker = "\u{0001}";
    $parts = explode($marker, __($key, [$param => $marker]), 2);
@endphp
{{ $parts[0] }}<strong>{{ $value }}</strong>{{ $parts[1] ?? '' }}
