<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Data\ProfilePanelData;
use App\Domain\Specialists\Enums\PublicationState;
use App\Domain\Specialists\Enums\VerificationStatus;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Domain\Specialists\Support\Grosze;
use App\Domain\Specialists\Support\ProfileCompleteness;

/**
 * Przygotowuje dla widoku panelu "Profil" gotowe dane: kompletność, stan karty publikacji, zapisany cennik,
 * etykietę miejscowości i stan zmiany adresu. Jeden zestaw zapytań (cennik, miejscowość z gminą, zaświadczenia).
 */
class BuildProfilePanelData
{
    public function handle(SpecialistProfile $profile): ProfilePanelData
    {
        $profile->loadMissing(['user', 'services', 'locality.municipality', 'certificates']);

        $completeness = ProfileCompleteness::for($profile);
        $locality = $profile->locality;

        $matrix = [];

        foreach ($profile->services as $service) {
            /** @var SpecialistService $service */
            $matrix[$service->inspection_type->value][$service->property_type->value] = [
                'enabled' => true,
                'price' => $service->price_grosze !== null ? Grosze::format($service->price_grosze) : '',
                'individual' => $service->price_grosze === null,
            ];
        }

        $slugChangedAt = $profile->first_published_at !== null ? $profile->slug_changed_at : null;

        return new ProfilePanelData(
            profile: $profile,
            completeness: $completeness,
            publicationState: $this->publicationState($profile, $completeness),
            publicUrl: $profile->publicUrl(),
            localityLabel: $locality !== null ? __('specialist.public.locality_label', [
                'name' => $locality->name,
                'municipality' => $locality->municipality->name,
                'county' => $locality->municipality->county_name,
            ]) : null,
            localityName: $locality?->name,
            servicesMatrix: $matrix,
            servicesInspectionCount: count($matrix),
            servicesPositionCount: $profile->services->count(),
            travelRateInput: Grosze::format($profile->travel_rate_per_km_grosze),
            slugRequiresConfirmation: $profile->first_published_at !== null,
            slugNextChangeAt: ChangeSpecialistSlug::nextChangeAt($profile),
            slugChangedAt: $slugChangedAt,
            nip: $profile->user->nip,
        );
    }

    /**
     * Stan karty "Publikacja" (kolejność reguł: zablokowany, opublikowany, niekompletny, gotowy/ukryty).
     */
    private function publicationState(SpecialistProfile $profile, ProfileCompleteness $completeness): PublicationState
    {
        if (! $profile->verification_status->isPubliclyVisible()) {
            return PublicationState::Blocked;
        }

        if ($profile->is_published) {
            return $profile->verification_status === VerificationStatus::Verified
                ? PublicationState::LiveVerified
                : PublicationState::LivePending;
        }

        if (! $completeness->isComplete()) {
            return PublicationState::Incomplete;
        }

        return $profile->first_published_at !== null ? PublicationState::Hidden : PublicationState::Ready;
    }
}
