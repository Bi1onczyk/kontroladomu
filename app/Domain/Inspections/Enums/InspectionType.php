<?php

namespace App\Domain\Inspections\Enums;

/**
 * Rodzaj okresowego przeglądu (ADR-017). Wartość enuma jest kluczem zapisywanym w bazie jako tekst.
 *
 * Dodanie rodzaju = nowy case + wpis w `lang/pl/inspections.php`, bez migracji.
 * Terminy przeglądów NIE są tu zapisane: pochodzą z tabeli `inspection_rules` (CLAUDE.md §9.7).
 */
enum InspectionType: string
{
    /** Przewody dymowe, spalinowe i wentylacyjne. */
    case Chimney = 'chimney';

    /** Instalacja elektryczna i piorunochronna. */
    case Electrical = 'electrical';

    /** Przegląd ogólnobudowlany roczny. */
    case BuildingAnnual = 'building_annual';

    /** Przegląd ogólnobudowlany pięcioletni. */
    case BuildingFiveYear = 'building_five_year';

    /** Instalacja gazowa. */
    case Gas = 'gas';

    /** Szamba (zbiorniki bezodpływowe) i przydomowe oczyszczalnie ścieków. */
    case Ecological = 'ecological';

    /**
     * Polska nazwa rodzaju przeglądu (lang/pl/inspections.php).
     */
    public function label(): string
    {
        return __('inspections.'.$this->value);
    }

    /**
     * Krótki opis rodzaju przeglądu do formularzy.
     */
    public function hint(): string
    {
        return __('inspections.hint.'.$this->value);
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
