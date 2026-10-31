<?php

namespace App\Domain\Specialists\Data;

use App\Domain\Specialists\Actions\BuildPublicSpecialistProfile;
use Carbon\CarbonInterface;

/**
 * Jedyne dane, jakie widok publicznej strony profilu (`specialists.show`) dostaje o fachowcu.
 *
 * Widok NIE dostaje modelu, więc nie może przypadkiem wypisać pola prywatnego. Obiekt nie zawiera:
 * NIP, e-maila, prywatnego telefonu (numer jest tu tylko po zgodzie fachowca), numerów i plików zaświadczeń,
 * współrzędnych ani identyfikatorów wewnętrznych. Buduje go {@see BuildPublicSpecialistProfile}.
 */
final readonly class PublicSpecialistData
{
    /**
     * @param  string  $slug  Bieżący adres profilu.
     * @param  string  $url  Pełny adres strony profilu.
     * @param  string  $accountTypeLabel  "Osoba prowadząca działalność" albo "Firma".
     * @param  bool  $isVerified  Czy status to `verified` (znaczek "Zweryfikowany").
     * @param  CarbonInterface|null  $verifiedAt  Data weryfikacji (tylko przy `verified`).
     * @param  string|null  $areaLabel  "Dąbrówka, gm. Dopiewo, pow. poznański"; null, gdy brak miejscowości (sekcja pomijana).
     * @param  int|null  $travelRateGrosze  Stawka za km w groszach; null, gdy 0 (profil nic wtedy nie mówi o dojeździe).
     * @param  string|null  $travelRateFormatted  Stawka do wyświetlenia, np. "1,50 zł"; null, gdy brak stawki.
     * @param  int|null  $travelFreeKm  Kilometry bezpłatne; null, gdy brak stawki lub 0.
     * @param  string|null  $phone  Numer do wyświetlenia ("600 123 456") tylko przy zgodzie fachowca; inaczej null.
     * @param  string|null  $phoneLink  Numer do linku `tel:` (np. "+48600123456") tylko przy zgodzie; inaczej null.
     * @param  list<PublicServiceGroup>  $serviceGroups  Cennik pogrupowany wg rodzaju przeglądu.
     * @param  list<PublicCertificate>  $certificates  Zaświadczenia sprawdzone przez administratora (puste, gdy brak lub status inny niż `verified`).
     * @param  bool  $certificatesPending  Czy pokazać linię "Uprawnienia: oczekują na weryfikację" (status `pending`).
     * @param  string  $metaTitle  Tytuł strony.
     * @param  string  $metaDescription  Opis meta.
     */
    public function __construct(
        public string $slug,
        public string $url,
        public string $displayName,
        public string $accountTypeLabel,
        public ?string $description,
        public bool $isVerified,
        public ?CarbonInterface $verifiedAt,
        public ?string $areaLabel,
        public ?string $localityName,
        public int $radiusKm,
        public ?int $travelRateGrosze,
        public ?string $travelRateFormatted,
        public ?int $travelFreeKm,
        public ?string $phone,
        public ?string $phoneLink,
        public array $serviceGroups,
        public array $certificates,
        public bool $certificatesPending,
        public string $metaTitle,
        public string $metaDescription,
    ) {}
}
