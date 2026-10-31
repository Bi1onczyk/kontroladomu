<?php

namespace App\Domain\Specialists\Data;

/**
 * Jedna pozycja cennika na stronie publicznej. `priceFormatted` jest null przy wycenie indywidualnej
 * (widok pokazuje wtedy tekst `specialist.public.price_individual`).
 */
final readonly class PublicServicePrice
{
    /**
     * @param  string  $propertyType  Klucz typu nieruchomości (wartość enuma `PropertyType`).
     * @param  string  $propertyLabel  Polska nazwa typu nieruchomości.
     * @param  int|null  $priceGrosze  Cena brutto w groszach; null = wycena indywidualna.
     * @param  string|null  $priceFormatted  Cena do wyświetlenia, np. "180,50 zł" (spacja twarda); null przy wycenie indywidualnej.
     */
    public function __construct(
        public string $propertyType,
        public string $propertyLabel,
        public ?int $priceGrosze,
        public ?string $priceFormatted,
    ) {}
}
