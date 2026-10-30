<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Zapamiętaj mnie" nie działa dla kont z wymogiem 2FA (ADR-009): sesja odtworzona z ciasteczka
 * omijałaby kod z aplikacji, więc taką sesję kończymy i wymagamy zwykłego logowania.
 */
class RejectRememberedPrivilegedSessions
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User && Auth::viaRemember() && $user->requiresTwoFactor()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
