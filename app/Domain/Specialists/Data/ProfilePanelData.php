<?php

namespace App\Domain\Specialists\Data;

use App\Domain\Specialists\Enums\PublicationState;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Support\ProfileCompleteness;
use Carbon\CarbonInterface;

/**
 * Dane widoku panelu "Profil" (`specialist.profile.edit`) przygotowane po stronie serwera.
 * Widok nie liczy kompletności ani stanów sam: bierze gotowe wartości stąd.
 */
final readonly class ProfilePanelData
{
    /**
     * @param  SpecialistProfile  $profile  Profil zalogowanego fachowca (relacje: cennik, miejscowość z gminą, zaświadczenia).
     * @param  ProfileCompleteness  $completeness  Kroki wymagane (`required()`, `missing()`, `requiredDone()`) i zalecane (`recommended()`).
     * @param  PublicationState  $publicationState  Stan karty "Publikacja".
     * @param  string  $publicUrl  Pełny adres strony profilu (podgląd).
     * @param  string|null  $localityLabel  "Dąbrówka, gm. Dopiewo, pow. poznański"; null, gdy miejscowość niewybrana.
     * @param  string|null  $localityName  Sama nazwa miejscowości (do komunikatów o położeniu).
     * @param  array<string, array<string, array{enabled: bool, price: string, individual: bool}>>  $servicesMatrix  Zapisany cennik: [rodzaj przeglądu][typ nieruchomości] => stan pozycji; cena jako tekst "180,50".
     * @param  int  $servicesInspectionCount  Liczba rodzajów przeglądów w cenniku (do podsumowania).
     * @param  int  $servicesPositionCount  Liczba pozycji w cenniku.
     * @param  string  $travelRateInput  Stawka za km w złotych jako tekst do pola formularza ("0", "1,50").
     * @param  bool  $slugRequiresConfirmation  Czy zmiana adresu wymaga zaznaczenia zgody (profil był już publikowany).
     * @param  CarbonInterface|null  $slugNextChangeAt  Najwcześniejsza następna zmiana adresu; null, gdy zmiana możliwa od razu.
     * @param  CarbonInterface|null  $slugChangedAt  Moment ostatniej zmiany adresu po publikacji.
     * @param  string|null  $nip  NIP konta (tylko do odczytu w formularzu; nigdy publicznie).
     */
    public function __construct(
        public SpecialistProfile $profile,
        public ProfileCompleteness $completeness,
        public PublicationState $publicationState,
        public string $publicUrl,
        public ?string $localityLabel,
        public ?string $localityName,
        public array $servicesMatrix,
        public int $servicesInspectionCount,
        public int $servicesPositionCount,
        public string $travelRateInput,
        public bool $slugRequiresConfirmation,
        public ?CarbonInterface $slugNextChangeAt,
        public ?CarbonInterface $slugChangedAt,
        public ?string $nip,
    ) {}
}
