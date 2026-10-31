<?php

namespace App\Domain\Specialists\Enums;

/**
 * Rodzaj zaświadczenia o uprawnieniach. Wyłącznie nazwy z `docs/PRZEPISY.md`; resztę obejmuje `other` + pole `title`.
 */
enum CertificateType: string
{
    /** Uprawnienia budowlane. */
    case BuildingPermit = 'building_permit';

    /** Świadectwo kwalifikacyjne (np. elektryczne, gazowe). */
    case QualificationCertificate = 'qualification_certificate';

    /** Mistrz kominiarski. */
    case MasterChimneySweep = 'master_chimney_sweep';

    /** Inne uprawnienie (opis w polu `title`). */
    case Other = 'other';

    /**
     * Polska nazwa rodzaju zaświadczenia.
     */
    public function label(): string
    {
        return __('certificates.type.'.$this->value);
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
