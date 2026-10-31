<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Inspections\Enums\InspectionType;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Specialists\Data\PublicCertificate;
use App\Domain\Specialists\Data\PublicServiceGroup;
use App\Domain\Specialists\Data\PublicServicePrice;
use App\Domain\Specialists\Data\PublicSpecialistData;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Domain\Specialists\Support\Grosze;
use App\Domain\Specialists\Support\PhoneNumber;

/**
 * Buduje {@see PublicSpecialistData}: jedyne dane, które widok publicznej strony profilu może wypisać.
 *
 * Reguły widoczności (zadanie 004, sekcja 4):
 * - telefon tylko przy `phone_is_public` (inaczej numeru nie ma w obiekcie, więc nie ma go w HTML),
 * - zaświadczenia (rodzaj, zakres, ważność) tylko dla profilu `verified` i tylko te z `updated_at <= verified_at`
 *   (zaświadczenie dodane lub zmienione po weryfikacji nie wygląda na sprawdzone, S6); numeru, wystawcy i pliku nigdy,
 * - informacja o dojeździe tylko przy stawce większej od 0,
 * - współrzędne nie są wypisywane w ogóle.
 *
 * Wczytuje relacje jednym zestawem zapytań (brak N+1): cennik, miejscowość z gminą i zaświadczenia.
 */
class BuildPublicSpecialistProfile
{
    public function handle(SpecialistProfile $profile): PublicSpecialistData
    {
        $profile->loadMissing(['services', 'locality.municipality', 'certificates']);

        $isVerified = $profile->verification_status === VerificationStatus::Verified;
        $groups = $this->serviceGroups($profile);
        $areaLabel = $this->areaLabel($profile);
        $localityName = $profile->locality?->name;
        $rate = $profile->travel_rate_per_km_grosze > 0 ? $profile->travel_rate_per_km_grosze : null;
        $showPhone = $profile->phone_is_public && filled($profile->phone);
        $typeLabels = implode(', ', array_map(fn (PublicServiceGroup $group): string => $group->label, $groups));

        return new PublicSpecialistData(
            slug: $profile->slug,
            url: $profile->publicUrl(),
            displayName: $profile->display_name,
            accountTypeLabel: $profile->account_type->publicLabel(),
            description: $profile->description,
            isVerified: $isVerified,
            verifiedAt: $isVerified ? $profile->verified_at : null,
            areaLabel: $areaLabel,
            localityName: $localityName,
            radiusKm: $profile->service_radius_km,
            travelRateGrosze: $rate,
            travelRateFormatted: $rate !== null ? Grosze::formatWithCurrency($rate) : null,
            travelFreeKm: $rate !== null && $profile->travel_free_km > 0 ? $profile->travel_free_km : null,
            phone: $showPhone ? PhoneNumber::display((string) $profile->phone) : null,
            phoneLink: $showPhone ? (string) $profile->phone : null,
            serviceGroups: $groups,
            certificates: $this->publicCertificates($profile, $isVerified),
            certificatesPending: $profile->verification_status === VerificationStatus::Pending,
            metaTitle: $localityName !== null
                ? __('specialist.public.meta_title', ['name' => $profile->display_name, 'types' => $typeLabels, 'locality' => $localityName])
                : __('specialist.public.meta_title_short', ['name' => $profile->display_name, 'types' => $typeLabels]),
            metaDescription: __('specialist.public.meta_description', [
                'name' => $profile->display_name,
                'locality' => $localityName ?? '',
                'types' => $typeLabels,
            ]),
        );
    }

    /**
     * Cennik pogrupowany wg rodzaju przeglądu; kolejność rodzajów i typów jak w enumach.
     *
     * @return list<PublicServiceGroup>
     */
    private function serviceGroups(SpecialistProfile $profile): array
    {
        $inspectionOrder = array_flip(InspectionType::values());
        $propertyOrder = array_flip(PropertyType::values());

        $sorted = $profile->services->sortBy([
            fn (SpecialistService $a, SpecialistService $b): int => $inspectionOrder[$a->inspection_type->value] <=> $inspectionOrder[$b->inspection_type->value],
            fn (SpecialistService $a, SpecialistService $b): int => $propertyOrder[$a->property_type->value] <=> $propertyOrder[$b->property_type->value],
        ]);

        $groups = [];

        foreach ($sorted->groupBy(fn (SpecialistService $service): string => $service->inspection_type->value) as $services) {
            /** @var SpecialistService $first */
            $first = $services->first();

            $groups[] = new PublicServiceGroup(
                inspectionType: $first->inspection_type->value,
                label: $first->inspection_type->label(),
                prices: $services->map(fn (SpecialistService $service): PublicServicePrice => new PublicServicePrice(
                    propertyType: $service->property_type->value,
                    propertyLabel: $service->property_type->label(),
                    priceGrosze: $service->price_grosze,
                    priceFormatted: $service->price_grosze !== null ? Grosze::formatWithCurrency($service->price_grosze) : null,
                ))->values()->all(),
            );
        }

        return $groups;
    }

    /**
     * Etykieta obszaru: "Dąbrówka, gm. Dopiewo, pow. poznański" (bez województwa); null, gdy brak miejscowości.
     */
    private function areaLabel(SpecialistProfile $profile): ?string
    {
        $locality = $profile->locality;

        if ($locality === null) {
            return null;
        }

        return __('specialist.public.locality_label', [
            'name' => $locality->name,
            'municipality' => $locality->municipality->name,
            'county' => $locality->municipality->county_name,
        ]);
    }

    /**
     * Zaświadczenia widoczne publicznie (S6): tylko przy statusie `verified` i tylko niezmienione po weryfikacji.
     *
     * @return list<PublicCertificate>
     */
    private function publicCertificates(SpecialistProfile $profile, bool $isVerified): array
    {
        if (! $isVerified || $profile->verified_at === null) {
            return [];
        }

        $verifiedAt = $profile->verified_at;

        return $profile->certificates
            ->filter(fn (SpecialistCertificate $certificate): bool => $certificate->updated_at !== null && $certificate->updated_at->lessThanOrEqualTo($verifiedAt))
            ->map(fn (SpecialistCertificate $certificate): PublicCertificate => new PublicCertificate(
                typeLabel: $certificate->type->label(),
                title: $certificate->title,
                validUntil: $certificate->valid_until,
            ))
            ->values()
            ->all();
    }
}
