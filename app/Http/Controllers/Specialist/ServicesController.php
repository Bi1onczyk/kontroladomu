<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Specialists\Actions\SyncSpecialistServices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\UpdateServicesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Sekcja "Usługi i cennik" w panelu fachowca.
 */
class ServicesController extends Controller
{
    /**
     * Zapisuje cennik (rodzaj przeglądu x typ nieruchomości x cena brutto w groszach).
     */
    public function update(UpdateServicesRequest $request, SyncSpecialistServices $action): RedirectResponse
    {
        $profile = $request->profile();
        Gate::authorize('update', $profile);

        /** @var User $user */
        $user = $request->user();
        $action->handle($profile, $request->serviceRows(), $user);

        return redirect()->to(route('specialist.profile.edit').'#sekcja-uslugi')
            ->with('status', __('specialist.services.saved'));
    }
}
