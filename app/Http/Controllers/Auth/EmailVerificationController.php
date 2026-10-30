<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Actions\ChangeUnverifiedEmail;
use App\Domain\Accounts\Actions\ConfirmPendingEmail;
use App\Domain\Accounts\Actions\VerifyEmailAddress;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Jobs\ProcessPendingEmailChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeEmailRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Potwierdzanie adresu e-mail: ekran informacyjny, ponowna wysyłka, kliknięcie w link, zmiana adresu.
 */
class EmailVerificationController extends Controller
{
    /**
     * Ekran "Sprawdź skrzynkę" dla zalogowanego z niepotwierdzonym adresem.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-notice', ['user' => $user, 'displayEmail' => $this->displayEmail($request, $user)]);
    }

    /**
     * Ponowna wysyłka wiadomości (limit: 1/min i 6/godz.).
     */
    public function resend(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        if ($user->pending_email !== null) {
            // Czeka zmiana adresu: ponowna wysyłka idzie tą samą ścieżką co pierwsza (neutralnie, w kolejce).
            ProcessPendingEmailChange::dispatch($user->getKey());
        } else {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', __('auth.verify.resent'));
    }

    /**
     * Kliknięcie w link z wiadomości. Link jest podpisany i NIE loguje użytkownika (ADR-006).
     * Wynik trafia do ekranu `verification.done` przez flash: `verify_status` oraz `verified_role`.
     */
    public function verify(Request $request, string $id, string $hash, VerifyEmailAddress $action): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('verification.done')->with('verify_status', VerifyEmailAddress::INVALID);
        }

        [$status, $user] = $action->handle($id, $hash);

        $redirect = redirect()->route('verification.done')->with('verify_status', $status);

        if ($user instanceof User) {
            $role = $user->primaryRole();
            if ($role === UserRole::Owner || $role === UserRole::Specialist) {
                $redirect->with('verified_role', $role->value);
            }
        }

        return $redirect;
    }

    /**
     * Kliknięcie w link potwierdzający NOWY adres (zmiana adresu przed potwierdzeniem). Nie loguje.
     * Wynik trafia na ten sam ekran `verification.done` (flash `verify_status`, `verified_role`).
     */
    public function verifyChange(Request $request, string $id, string $hash, ConfirmPendingEmail $action): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('verification.done')->with('verify_status', VerifyEmailAddress::INVALID);
        }

        [$status, $user] = $action->handle($id, $hash);

        $redirect = redirect()->route('verification.done')->with('verify_status', $status);

        if ($user instanceof User) {
            $role = $user->primaryRole();
            if ($role === UserRole::Owner || $role === UserRole::Specialist) {
                $redirect->with('verified_role', $role->value);
            }
        }

        return $redirect;
    }

    /**
     * Ekran wyniku potwierdzenia (dla zalogowanych i niezalogowanych).
     */
    public function done(): View
    {
        return view('auth.verify-done', [
            'status' => session('verify_status', VerifyEmailAddress::INVALID),
            'verifiedRole' => session('verified_role'),
        ]);
    }

    /**
     * Formularz zmiany adresu (tylko konto niepotwierdzone).
     */
    public function changeForm(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->hasVerifiedEmail(), 403);

        return view('auth.change-email', ['user' => $user, 'displayEmail' => $this->displayEmail($request, $user)]);
    }

    /**
     * Adres pokazywany na ekranach potwierdzenia: oczekujący (wpisany w formularzu zmiany) albo adres konta.
     */
    private function displayEmail(Request $request, User $user): string
    {
        return $user->pending_email ?? $user->email;
    }

    /**
     * Zapisuje nowy adres i wysyła nowy link.
     */
    public function change(ChangeEmailRequest $request, ChangeUnverifiedEmail $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $email = (string) $request->validated('email');

        $action->handle($user, $email);

        return redirect()->route('verification.notice')
            ->with('status', __('auth.email_change.success', ['email' => $email]));
    }
}
