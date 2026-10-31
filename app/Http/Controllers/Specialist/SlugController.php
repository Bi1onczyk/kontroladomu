<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Specialists\Actions\ChangeSpecialistSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\ChangeSlugRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Sekcja "Adres profilu" w panelu fachowca.
 */
class SlugController extends Controller
{
    /**
     * Zmienia adres profilu (limit: raz na 30 dni po pierwszej publikacji; stary adres przekierowuje 301).
     */
    public function update(ChangeSlugRequest $request, ChangeSpecialistSlug $action): RedirectResponse
    {
        $profile = $request->profile();
        Gate::authorize('update', $profile);

        /** @var User $user */
        $user = $request->user();
        $profile = $action->handle($profile, $request->newSlug(), $user);

        return redirect()->to(route('specialist.profile.edit').'#sekcja-adres')
            ->with('status', __('specialist.slug.saved', ['url' => $profile->publicUrl()]));
    }
}
