<?php

use App\Http\Middleware\EnsureAccountIsNotBanned;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\RejectRememberedPrivilegedSessions;
use App\Http\Middleware\RejectRevokedSessions;
use App\Http\Middleware\RequireTwoFactorForPrivilegedRoles;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\TwoFactorChallengeGuard;
use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Zaufane proxy (zakresy IP Cloudflare) z config('security.trusted_proxies'), czytane w czasie żądania.
        // UWAGA: tu NIE wolno wołać env() - ten callback działa przed wczytaniem .env.
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);

        // Tylko host z APP_URL (bez subdomen) jest akceptowany; obcy nagłówek Host dostaje błąd 400.
        $middleware->trustHosts(
            // APP_URL bez hosta: wzorzec '^$' nie pasuje do żadnego hosta (fail-closed), zamiast wyłączać ochronę.
            at: fn (): array => [($host = parse_url((string) config('app.url'), PHP_URL_HOST)) ? '^'.preg_quote($host, '/').'$' : '^$'],
            subdomains: false,
        );

        $middleware->alias([
            'turnstile' => VerifyTurnstile::class,
            'two-factor.required' => RequireTwoFactorForPrivilegedRoles::class,
            'two-factor.guard' => TwoFactorChallengeGuard::class,
            'no-store' => NoStore::class,
        ]);

        // Nagłówki bezpieczeństwa na każdej odpowiedzi (także błędy 404/500).
        $middleware->append(SecurityHeaders::class);

        // Po StartSession: sesja z ciasteczka "zapamiętaj mnie" nie omija 2FA.
        // Hasło zapisane w sesji: zmiana lub reset hasła kończy też sesje na innych urządzeniach,
        // niezależnie od sterownika sesji (database, file...).
        $middleware->appendToGroup('web', AuthenticateSession::class);

        // Reset 2FA / hasła podnosi wersję sesji konta: starsze sesje są kończone (każdy sterownik sesji).
        $middleware->appendToGroup('web', RejectRevokedSessions::class);

        $middleware->appendToGroup('web', RejectRememberedPrivilegedSessions::class);

        // Konto zbanowane traci dostęp przy najbliższym żądaniu (także sesja "zapamiętaj mnie").
        $middleware->appendToGroup('web', EnsureAccountIsNotBanned::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Wygasły token CSRF (419): zamiast strony błędu wracamy do logowania z komunikatem.
        // Laravel zamienia TokenMismatchException na HttpException 419 przed wywołaniem tej funkcji.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return redirect()->route('login')->with('status', __('auth.session_expired'));
        });
    })->create();
