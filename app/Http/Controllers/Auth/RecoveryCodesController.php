<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kody awaryjne 2FA: pokazanie, pobranie jako plik tekstowy i wygenerowanie nowych.
 * Wszystkie trasy wymagają świeżego potwierdzenia hasła (`password.confirm`).
 */
class RecoveryCodesController extends Controller
{
    /**
     * Pokazuje kody awaryjne (przekierowanie na ekran bezpieczeństwa z flagą wyświetlenia).
     */
    public function show(Request $request): RedirectResponse
    {
        $user = $this->authorizedUser($request);

        abort_unless($user->hasConfirmedTwoFactor(), 404);

        return redirect()->route('account.security')->with('two_factor_show_codes', true);
    }

    /**
     * Pobranie kodów jako plik tekstowy. Trasa serwerowa (nie Blob w JS), bez cache'owania.
     */
    public function download(Request $request): Response
    {
        $user = $this->authorizedUser($request);

        abort_unless($user->hasConfirmedTwoFactor(), 404);

        $content = implode("\n", $user->recoveryCodes())."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kontroladomu-kody-awaryjne.txt"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Generuje nowy komplet kodów (poprzednie przestają działać) i pokazuje je.
     */
    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $user = $this->authorizedUser($request);

        abort_unless($user->hasConfirmedTwoFactor(), 404);

        $generate($user);

        return redirect()->route('account.security')->with('two_factor_show_codes', true);
    }

    /**
     * Zalogowany użytkownik z potwierdzoną zgodą Policy na zarządzanie własnym bezpieczeństwem.
     */
    private function authorizedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('manageSecurity', $user);

        return $user;
    }
}
