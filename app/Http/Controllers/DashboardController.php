<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Support\ProfileCompleteness;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Pulpit po zalogowaniu. Widok zależy od roli głównej konta
 * (dashboard.owner / specialist / technician / admin).
 */
class DashboardController extends Controller
{
    /**
     * Pokazuje pulpit odpowiedni dla roli głównej.
     */
    public function __invoke(Request $request, EnsureSpecialistProfile $ensureProfile): View
    {
        /** @var User $user */
        $user = $request->user();
        $role = $user->primaryRole();

        abort_if($role === null, 403);

        $data = ['user' => $user, 'role' => $role];

        // Pulpit fachowca: kroki profilu liczy ProfileCompleteness (wymagane `data|services|area`, zalecane `description|certificates`).
        // Profil powstaje przy pierwszym wejściu (idempotentnie), więc `completeness` jest zawsze ustawione.
        if ($role === UserRole::Specialist) {
            $profile = $ensureProfile->handle($user);
            $data['profile'] = $profile;
            $data['completeness'] = $profile !== null ? ProfileCompleteness::for($profile) : null;
        }

        return view('dashboard.'.$role->value, $data);
    }
}
