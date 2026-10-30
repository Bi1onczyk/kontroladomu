<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kończy sesje unieważnione przez administratora lub użytkownika (reset 2FA, zmiana/reset hasła).
 *
 * Konto ma licznik `session_version`; sesja zapamiętuje wersję z pierwszego żądania po zalogowaniu.
 * Gdy licznik w bazie jest większy, sesja jest nieważna. Działa dla każdego sterownika sesji
 * (także `file`), w odróżnieniu od usuwania wierszy tabeli `sessions`.
 */
class RejectRevokedSessions
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();

        if (! $session->has('session_version')) {
            $session->put('session_version', (int) $user->session_version);

            return $next($request);
        }

        if ((int) $session->get('session_version') !== (int) $user->session_version) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
