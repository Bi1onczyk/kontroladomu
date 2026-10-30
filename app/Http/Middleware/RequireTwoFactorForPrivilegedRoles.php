<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trzecia bramka panelu (po `auth` i `verified`): konto z rolą wymagającą 2FA (fachowiec, administrator)
 * nie wejdzie do panelu, dopóki nie potwierdzi weryfikacji dwuetapowej (ADR-009).
 *
 * Trasy konfiguracji 2FA, potwierdzenia hasła, weryfikacji e-mail i wylogowania leżą POZA tą bramką.
 */
class RequireTwoFactorForPrivilegedRoles
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->requiresTwoFactor() || $user->hasConfirmedTwoFactor()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __('auth.twofa.required_text'));
        }

        // Flaga "tryb wymuszony": widok ukrywa menu panelu i pokazuje tylko kreator 2FA.
        return redirect()->route('account.security')->with('two_factor_required', true);
    }
}
