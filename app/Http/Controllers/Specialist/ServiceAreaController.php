<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Actions\RetrySpecialistGeocoding;
use App\Domain\Specialists\Actions\UpdateSpecialistServiceArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\UpdateServiceAreaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Sekcja "Obszar działania i dojazd" w panelu fachowca.
 */
class ServiceAreaController extends Controller
{
    /**
     * Zapisuje miejscowość startową, promień i koszt dojazdu. Geokodowanie (jeśli potrzebne) idzie do kolejki.
     */
    public function update(UpdateServiceAreaRequest $request, UpdateSpecialistServiceArea $action): RedirectResponse
    {
        $profile = $request->profile();
        Gate::authorize('update', $profile);

        /** @var User $user */
        $user = $request->user();
        $action->handle($profile, $request->areaData(), $user);

        return redirect()->to(route('specialist.profile.edit').'#sekcja-obszar')
            ->with('status', __('specialist.area.saved'));
    }

    /**
     * "Spróbuj ponownie": ponawia ustalanie położenia miejscowości (limit 3 na godzinę na użytkownika w trasie).
     */
    public function retry(Request $request, EnsureSpecialistProfile $ensure, RetrySpecialistGeocoding $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $ensure->handle($user);
        abort_if($profile === null, 403);
        Gate::authorize('update', $profile);

        $action->handle($profile, $user);

        return redirect()->to(route('specialist.profile.edit').'#sekcja-obszar')
            ->with('status', __('specialist.area.status.retry_done'));
    }
}
