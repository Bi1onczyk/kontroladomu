<?php

namespace App\Domain\Geo\Data;

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Support\SearchText;

/**
 * Adres do geokodowania. Do zewnętrznej usługi trafiają WYŁĄCZNIE pola adresowe (ulica, numer,
 * kod pocztowy, miejscowość, powiat, województwo, kraj): nigdy dane osoby, e-mail, telefon ani ID użytkownika.
 */
final readonly class AddressQuery
{
    /**
     * @param  string|null  $simc  Kod TERYT miejscowości; rozróżnia miejscowości o tej samej nazwie (w skrócie cache).
     * @param  string|null  $county  Nazwa powiatu w formie OSM (np. "powiat poznański"); doprecyzowuje miejscowość.
     * @param  string|null  $state  Nazwa województwa w formie OSM (np. "województwo wielkopolskie").
     */
    public function __construct(
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $simc = null,
        public ?string $county = null,
        public ?string $state = null,
    ) {}

    /**
     * Zapytanie o środek miejscowości z bazy TERYT (bez ulicy). Powiat i województwo zawężają wynik,
     * bo nazw typu "Dąbrówka" jest w Polsce ponad sto.
     *
     * UWAGA: forma nazw powiatu i województwa ("powiat poznański", "województwo wielkopolskie") jest oparta
     * na konwencji nazewnictwa OpenStreetMap i NIE była sprawdzona na żywym Nominatim. Powiat z wielkiej litery
     * (miasto na prawach powiatu, np. "Poznań") przekazujemy bez przedrostka.
     */
    public static function forLocality(TerytLocality $locality): self
    {
        $municipality = $locality->municipality;
        $county = $municipality->county_name;
        $isCountyWithOwnName = mb_strtolower($county) !== $county;

        return new self(
            city: $locality->name,
            simc: $locality->simc,
            county: $isCountyWithOwnName ? $county : 'powiat '.$county,
            state: 'województwo '.$municipality->voivodeship_name,
        );
    }

    /**
     * Ten sam adres bez ulicy i numeru (zapytanie awaryjne: środek miejscowości).
     */
    public function withoutStreet(): self
    {
        return new self(
            postalCode: $this->postalCode,
            city: $this->city,
            simc: $this->simc,
            county: $this->county,
            state: $this->state,
        );
    }

    /**
     * Czy zapytanie zawiera cokolwiek, po czym da się szukać (miejscowość, kod pocztowy albo ulica).
     */
    public function isSearchable(): bool
    {
        return filled($this->city) || filled($this->postalCode) || filled($this->street);
    }

    /**
     * Czy zapytanie ma ulicę (wtedy po braku wyniku warto spróbować samej miejscowości).
     */
    public function hasStreet(): bool
    {
        return filled($this->street);
    }

    /**
     * Skrót SHA-256 znormalizowanego adresu: klucz cache. Tekst adresu nie jest nigdzie zapisywany.
     */
    public function hash(): string
    {
        return hash('sha256', implode('|', [
            SearchText::normalize((string) $this->street),
            SearchText::normalize((string) $this->houseNumber),
            preg_replace('/\D+/', '', (string) $this->postalCode),
            SearchText::normalize((string) $this->city),
            SearchText::normalize((string) $this->simc),
            SearchText::normalize((string) $this->county),
            SearchText::normalize((string) $this->state),
        ]));
    }
}
