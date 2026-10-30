<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Listeners\TwoFactorChallengeListeners;
use App\Domain\Accounts\Support\TwoFactorLockout;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strażnik ekranu wyzwania 2FA przy logowaniu:
 * 1) po 10 minutach od poprawnego hasła wyzwanie wygasa (powrót do logowania),
 * 2) po 5 błędnych kodach blokada na 15 minut (HTTP 429, nagłówek Retry-After).
 */
class TwoFactorChallengeGuard
{
    public function __construct(private readonly TwoFactorLockout $lockout) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $userId = $session->get('login.id');

        if ($userId === null) {
            // Brak wyzwania w sesji: Fortify sam przekieruje na logowanie.
            return $next($request);
        }

        // Konto zbanowane w trakcie wyzwania nie może się zalogować nawet poprawnym kodem.
        if (User::query()->whereKey($userId)->whereNotNull('banned_at')->exists()) {
            $session->forget(['login.id', 'login.remember', TwoFactorChallengeListeners::SESSION_KEY]);

            return redirect()->route('login')->withErrors([
                'email' => __('auth.login.blocked', ['email' => config('app.contact_email')]),
            ]);
        }

        $startedAt = (int) $session->get(TwoFactorChallengeListeners::SESSION_KEY, 0);

        if (now()->getTimestamp() - $startedAt > TwoFactorChallengeListeners::CHALLENGE_SECONDS) {
            $session->forget(['login.id', 'login.remember', TwoFactorChallengeListeners::SESSION_KEY]);

            return redirect()->route('login')->with('status', __('auth.twofa.challenge_expired'));
        }

        if ($this->lockout->isLocked($userId)) {
            $seconds = $this->lockout->secondsLeft($userId);

            return response()
                ->view('auth.two-factor-locked', [
                    'secondsLeft' => $seconds,
                    'minutesLeft' => (int) ceil($seconds / 60),
                ], 429)
                ->header('Retry-After', (string) $seconds);
        }

        return $next($request);
    }
}
