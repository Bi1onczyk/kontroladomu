<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Actions\DismissTwoFactorReminder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

/**
 * Ekran "Hasło i bezpieczeństwo" oraz kreator weryfikacji dwuetapowej (2FA).
 *
 * Kreator to zwykłe formularze POST (bez maszyny stanów w JS, ADR-007):
 * krok 1 (informacja) -> POST enable -> krok 2 (QR + kod) -> POST confirm -> krok 3 (kody awaryjne).
 */
class TwoFactorSettingsController extends Controller
{
    /**
     * Pokazuje ekran bezpieczeństwa. Zależnie od stanu konta widok dostaje:
     * `step` ('intro' | 'scan' | 'codes' | 'manage'), `qrDataUri`, `otpauthUrl`, `secretKey`,
     * `recoveryCodes` (tylko w kroku 'codes'), `forced` (tryb wymuszony), `codesLow`.
     */
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('manageSecurity', $user);

        $step = 'intro';
        $data = [];

        if ($user->hasConfirmedTwoFactor()) {
            $step = 'manage';

            if ($request->session()->pull('two_factor_show_codes')) {
                $step = 'codes';
                $data['recoveryCodes'] = $user->recoveryCodes();
            }
        } elseif (! empty($user->two_factor_secret)) {
            // Sekret wygenerowany, ale niepotwierdzony: ponowne wejście używa tego samego sekretu.
            $step = 'scan';
            $data['qrDataUri'] = 'data:image/svg+xml;base64,'.base64_encode($user->twoFactorQrCodeSvg());
            $data['otpauthUrl'] = $user->twoFactorQrCodeUrl();
            $data['secretKey'] = trim(chunk_split(
                (string) Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                4,
                ' ',
            ));
        }

        $remaining = $user->hasConfirmedTwoFactor() ? count($user->recoveryCodes()) : null;

        return view('account.security', $data + [
            'user' => $user,
            'step' => $step,
            'forced' => $user->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor(),
            'recoveryCodes' => null,
            'codesRemaining' => $remaining,
            'codesLow' => $remaining !== null && $remaining < 3,
        ]);
    }

    /**
     * Krok 1 -> 2: generuje i zapisuje (niepotwierdzony) sekret oraz kody awaryjne.
     */
    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('manageSecurity', $user);

        if (! $user->hasConfirmedTwoFactor()) {
            $enable($user);
        }

        return redirect()->route('account.security');
    }

    /**
     * Krok 2 -> 3: sprawdza kod z aplikacji i oznacza 2FA jako potwierdzone.
     */
    public function confirm(ConfirmTwoFactorRequest $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('manageSecurity', $user);

        try {
            $confirm($user, (string) $request->validated('code'));
        } catch (ValidationException) {
            // Własny komunikat w domyślnym worku błędów (pole `code`).
            throw ValidationException::withMessages(['code' => __('auth.twofa.code_invalid')]);
        }

        $request->session()->flash('two_factor_show_codes', true);

        return redirect()->route('account.security')->with('status', __('auth.twofa.enabled_flash'));
    }

    /**
     * Wyłącza 2FA. Polityka blokuje to dla ról, które go wymagają (fachowiec, administrator).
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('disableTwoFactor', $user);

        $disable($user);

        return redirect()->route('account.security');
    }

    /**
     * "Przypomnij później" na banerze 2FA właściciela (30 dni).
     */
    public function dismissReminder(Request $request, DismissTwoFactorReminder $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user);

        return back();
    }
}
