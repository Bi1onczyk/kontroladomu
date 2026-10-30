<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Konto zbanowane (`banned_at`) traci dostęp przy najbliższym żądaniu: wylogowanie, unieważnienie sesji
 * i komunikat o blokadzie. Dotyczy też sesji odtworzonej z ciasteczka "zapamiętaj mnie".
 */
class EnsureAccountIsNotBanned
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User && $user->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('auth.login.blocked', ['email' => config('app.contact_email')]));
            }

            return redirect()->route('login')->withErrors([
                'email' => __('auth.login.blocked', ['email' => config('app.contact_email')]),
            ]);
        }

        return $next($request);
    }
}
