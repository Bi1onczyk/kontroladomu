<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Actions\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Zmiana hasła przez zalogowanego użytkownika (ekran "Hasło i bezpieczeństwo").
 */
class PasswordSettingsController extends Controller
{
    /**
     * Zapisuje nowe hasło; bieżąca sesja zostaje, pozostałe są kończone.
     */
    public function update(UpdatePasswordRequest $request, UpdateUserPassword $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, (string) $request->validated('password'), $request->session()->getId());

        // Bieżąca sesja ma dostać nowy skrót hasła, inaczej AuthenticateSession wylogowałby też ją.
        $request->session()->put('password_hash_'.config('auth.defaults.guard'), $user->getAuthPassword());
        $request->session()->put('session_version', (int) $user->session_version);

        return redirect()->route('account.security')->with('status', __('auth.security.success'));
    }
}
