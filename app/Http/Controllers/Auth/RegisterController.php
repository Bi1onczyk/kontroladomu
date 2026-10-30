<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Accounts\Actions\RegisterAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterOwnerRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\RegisterSpecialistRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Rejestracja kont: wybór rodzaju konta, formularze (właściciel / fachowiec) i ekran "Sprawdź skrzynkę".
 * Cienki kontroler: logika w akcji RegisterAccount.
 */
class RegisterController extends Controller
{
    /**
     * Ekran wyboru rodzaju konta (dwa linki-bloki).
     */
    public function choose(): View
    {
        return view('auth.register-choose');
    }

    /**
     * Formularz rejestracji właściciela.
     */
    public function showOwner(): View
    {
        return view('auth.register-owner');
    }

    /**
     * Formularz rejestracji fachowca.
     */
    public function showSpecialist(): View
    {
        return view('auth.register-specialist');
    }

    /**
     * Zapisuje konto właściciela.
     */
    public function storeOwner(RegisterOwnerRequest $request, RegisterAccount $action): RedirectResponse
    {
        return $this->register($request, $action);
    }

    /**
     * Zapisuje konto fachowca.
     */
    public function storeSpecialist(RegisterSpecialistRequest $request, RegisterAccount $action): RedirectResponse
    {
        return $this->register($request, $action);
    }

    /**
     * Ekran "Sprawdź skrzynkę" (dla niezalogowanych). Adres bierzemy z sesji flash; może go nie być.
     */
    public function sent(): View
    {
        return view('auth.register-sent', ['email' => session('registered_email')]);
    }

    /**
     * Wspólna część: wykonuje akcję i zawsze przekierowuje na ten sam ekran (odpowiedź neutralna).
     */
    private function register(RegisterRequest $request, RegisterAccount $action): RedirectResponse
    {
        $data = $request->toData();

        $action->handle($data);

        return redirect()->route('register.sent')->with('registered_email', $data->email);
    }
}
