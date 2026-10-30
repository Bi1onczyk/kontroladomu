<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;

/**
 * Odpowiedź na nieudane ustawienie nowego hasła (zły token, nieistniejący adres, token po terminie).
 *
 * Zawsze ten sam komunikat (`passwords.token`), niezależnie od statusu brokera haseł,
 * żeby nie ujawniać, czy konto z danym adresem istnieje.
 */
class NeutralFailedPasswordResetResponse implements FailedPasswordResetResponse
{
    /**
     * @param  string  $status  Status z brokera haseł (celowo ignorowany).
     */
    public function __construct(protected string $status = '') {}

    /**
     * Tworzy odpowiedź HTTP.
     *
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        $message = __('passwords.token');

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => $message]);
    }
}
