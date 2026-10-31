<?php

namespace App\Domain\Specialists\Data;

use Carbon\CarbonInterface;

/**
 * Zaświadczenie widoczne publicznie: tylko rodzaj, zakres i data ważności.
 * Numer, wystawca, plik i miniatura NIGDY nie trafiają do tego obiektu (ADR-019).
 */
final readonly class PublicCertificate
{
    /**
     * @param  string  $typeLabel  Polska nazwa rodzaju zaświadczenia.
     * @param  string  $title  Zakres uprawnienia.
     * @param  CarbonInterface|null  $validUntil  Data ważności; null = bezterminowo.
     */
    public function __construct(
        public string $typeLabel,
        public string $title,
        public ?CarbonInterface $validUntil,
    ) {}
}
