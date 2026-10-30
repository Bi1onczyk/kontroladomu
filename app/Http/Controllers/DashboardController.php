<?php

namespace App\Http\Controllers;

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
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $role = $user->primaryRole();

        abort_if($role === null, 403);

        return view('dashboard.'.$role->value, ['user' => $user, 'role' => $role]);
    }
}
