<?php

namespace App\Domain\Specialists\Data;

/**
 * Grupa pozycji cennika dla jednego rodzaju przeglądu (nagłówek H3 i tabela "typ nieruchomości | cena").
 */
final readonly class PublicServiceGroup
{
    /**
     * @param  string  $inspectionType  Klucz rodzaju przeglądu (wartość enuma `InspectionType`).
     * @param  string  $label  Polska nazwa rodzaju przeglądu.
     * @param  list<PublicServicePrice>  $prices  Pozycje w kolejności typów nieruchomości.
     */
    public function __construct(
        public string $inspectionType,
        public string $label,
        public array $prices,
    ) {}
}
