<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Actions\PublishSpecialistProfile;
use App\Domain\Specialists\Actions\UnpublishSpecialistProfile;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Karta "Publikacja": opublikowanie i ukrycie profilu. Bez okien potwierdzenia (obie czynności są odwracalne).
 */
class PublicationController extends Controller
{
    /**
     * Publikuje profil. Brak wymaganych kroków kończy się błędem walidacji z kluczami `publish.data|services|area`.
     */
    public function store(Request $request, EnsureSpecialistProfile $ensure, PublishSpecialistProfile $action): RedirectResponse
    {
        [$profile, $user] = $this->profileAndUser($request, $ensure);
        Gate::authorize('publish', $profile);

        $profile = $action->handle($profile, $user);

        return redirect()->to(route('specialist.profile.edit').'#publikacja')
            ->with('status', __('specialist.publish.flash_published', ['url' => $profile->publicUrl()]));
    }

    /**
     * Ukrywa profil.
     */
    public function destroy(Request $request, EnsureSpecialistProfile $ensure, UnpublishSpecialistProfile $action): RedirectResponse
    {
        [$profile, $user] = $this->profileAndUser($request, $ensure);
        Gate::authorize('update', $profile);

        $action->handle($profile, $user);

        return redirect()->to(route('specialist.profile.edit').'#publikacja')
            ->with('status', __('specialist.publish.flash_unpublished'));
    }

    /**
     * @return array{0: SpecialistProfile, 1: User}
     */
    private function profileAndUser(Request $request, EnsureSpecialistProfile $ensure): array
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $ensure->handle($user);
        abort_if($profile === null, 403);

        return [$profile, $user];
    }
}
