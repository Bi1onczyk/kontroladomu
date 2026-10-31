<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Inspections\Enums\InspectionType;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Specialists\Actions\BuildProfilePanelData;
use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Actions\UpdateSpecialistProfile;
use App\Domain\Specialists\Enums\AccountType;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Panel fachowca: strona "Profil" (jedna strona z sekcjami) i zapis sekcji "Dane firmy i opis".
 *
 * Pozostałe sekcje mają własne kontrolery (cennik, obszar, adres, publikacja). Profil jest zawsze profilem
 * zalogowanego użytkownika; żadne ID profilu nie pochodzi z adresu ani z formularza.
 */
class ProfileController extends Controller
{
    /**
     * Pokazuje stronę "Profil". Widok `specialist.profile.edit` dostaje:
     * - `panel` (ProfilePanelData): profil, kompletność, stan publikacji, cennik, etykieta miejscowości, stan zmiany adresu,
     * - `inspectionTypes`, `propertyTypes`, `accountTypes`: listy enumów do pól formularza,
     * - `limits`: zakresy do podpowiedzi (promień, cena, opis).
     */
    public function edit(Request $request, BuildProfilePanelData $builder): View
    {
        $profile = $this->profileOf($request);
        Gate::authorize('update', $profile);

        return view('specialist.profile.edit', [
            'panel' => $builder->handle($profile),
            'inspectionTypes' => InspectionType::cases(),
            'propertyTypes' => PropertyType::cases(),
            'accountTypes' => AccountType::cases(),
            'limits' => [
                'radius_min_km' => (int) config('specialists.radius_min_km'),
                'radius_max_km' => (int) config('specialists.radius_max_km'),
                'price_min_grosze' => (int) config('specialists.price_min_grosze'),
                'price_max_grosze' => (int) config('specialists.price_max_grosze'),
                'travel_rate_max_grosze' => (int) config('specialists.travel_rate_max_grosze'),
                'description_max_length' => (int) config('specialists.description_max_length'),
                'display_name_max_length' => (int) config('specialists.display_name_max_length'),
                'slug_min_length' => (int) config('specialists.slug_min_length'),
                'slug_max_length' => (int) config('specialists.slug_max_length'),
            ],
        ]);
    }

    /**
     * Zapisuje sekcję "Dane firmy i opis" i wraca do niej.
     */
    public function update(UpdateProfileRequest $request, UpdateSpecialistProfile $action): RedirectResponse
    {
        $profile = $request->profile();
        Gate::authorize('update', $profile);

        /** @var User $user */
        $user = $request->user();
        $data = $request->profileData();
        $consentWasPublic = $profile->phone_is_public;
        $profile = $action->handle($profile, $data, $user);

        // Zmiana numeru cofnęła zgodę na publikację: mówimy o tym wprost, żeby fachowiec zaznaczył ją ponownie.
        $consentReset = $consentWasPublic && $data['phone_is_public'] && ! $profile->phone_is_public;

        return redirect()->to(route('specialist.profile.edit').'#sekcja-dane')
            ->with('status', __($consentReset ? 'specialist.data.saved_phone_consent_reset' : 'specialist.data.saved'));
    }

    /**
     * Profil zalogowanego fachowca (zakładany przy pierwszym wejściu).
     */
    private function profileOf(Request $request): SpecialistProfile
    {
        $profile = app(EnsureSpecialistProfile::class)->handle($request->user());
        abort_if($profile === null, 403);

        return $profile;
    }
}
