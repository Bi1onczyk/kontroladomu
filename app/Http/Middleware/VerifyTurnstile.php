<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Support\LoginFailureCounter;
use App\Services\Turnstile\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wymaga poprawnego tokenu Turnstile (pole `cf-turnstile-response`).
 *
 * Parametry trasy: `turnstile:always` (zawsze) albo `turnstile:after-failures`
 * (dopiero po kilku nieudanych logowaniach z tego IP albo na to konto).
 */
class VerifyTurnstile
{
    public function __construct(
        private readonly TurnstileVerifier $verifier,
        private readonly LoginFailureCounter $failures,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next, string $mode = 'always'): Response
    {
        $email = $request->input('email');
        $required = $mode === 'always'
            || $this->failures->requiresTurnstile((string) $request->ip(), is_string($email) ? $email : null);

        $token = $request->input('cf-turnstile-response');

        if ($required && ! $this->verifier->verify(is_string($token) ? $token : null, $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => __('auth.turnstile_invalid'),
            ]);
        }

        return $next($request);
    }
}
