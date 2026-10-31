<?php

namespace App\Domain\Geo\Enums;

/**
 * Rodzaj miejscowości (pole RM w pliku SIMC). Mapowanie kodu na rodzaj jest tylko tutaj.
 * Kody sprawdzone na prawdziwym pliku GUS (słownik WMRODZ z 30.10.2026).
 */
enum LocalityKind: string
{
    case City = 'city';
    case Village = 'village';
    case Colony = 'colony';
    case Hamlet = 'hamlet';
    case Settlement = 'settlement';
    case ForestSettlement = 'forest_settlement';
    case District = 'district';
    case Part = 'part';
    case Other = 'other';

    /**
     * Rodzaj odpowiadający kodowi RM z SIMC; nieznany kod daje `Other` (import nie przerywa się
     * z powodu nowego kodu wprowadzonego przez GUS).
     */
    public static function fromSimcCode(string $rm): self
    {
        return match ($rm) {
            '00', '99' => self::Part,
            '01' => self::Village,
            '02' => self::Colony,
            '03' => self::Hamlet,
            '04' => self::Settlement,
            '05' => self::ForestSettlement,
            '95', '98' => self::District,
            '96' => self::City,
            default => self::Other,
        };
    }

    /**
     * Polska nazwa rodzaju (np. "wieś") z `lang/pl/geo.php`.
     */
    public function label(): string
    {
        return __('geo.kinds.'.$this->value);
    }
}
