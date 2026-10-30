<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Neutralna odpowiedź na prośbę o link do resetu hasła.
 *
 * Zawsze ten sam komunikat (`passwords.sent`), niezależnie od wyniku brokera haseł
 * (konto istnieje, nie istnieje, limit 60 s) — żeby nie ujawniać istnienia kont.
 */
class NeutralPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
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
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        $message = __('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
