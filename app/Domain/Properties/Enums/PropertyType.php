<?php

namespace App\Domain\Properties\Enums;

/**
 * Typ nieruchomości (ADR-017). Wartość enuma jest kluczem zapisywanym w bazie jako tekst.
 */
enum PropertyType: string
{
    case SingleFamilyHouse = 'single_family_house';
    case Apartment = 'apartment';
    case CommercialUnit = 'commercial_unit';
    case MultiFamilyBuilding = 'multi_family_building';

    /** Zabudowa powyżej 2000 m2 albo dach powyżej 1000 m2. */
    case LargeAreaBuilding = 'large_area_building';

    /**
     * Polska nazwa typu nieruchomości (lang/pl/properties.php).
     */
    public function label(): string
    {
        return __('properties.'.$this->value);
    }

    /**
     * Dodatkowy opis typu (tylko dla niektórych typów), inaczej null.
     */
    public function hint(): ?string
    {
        $key = 'properties.hint.'.$this->value;
        $text = __($key);

        return $text === $key ? null : $text;
    }

    /**
     * Wszystkie wartości (do reguły `Rule::in`).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
