<?php

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dodaje do każdej odpowiedzi nagłówki bezpieczeństwa (CSP z nonce, X-Frame-Options, HSTS itd.).
 *
 * Nonce jest dostępny w widokach jako `$cspNonce` oraz w `Vite::cspNonce()`.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(Str::random(24));

        Vite::useCspNonce($nonce);
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        /** @var array<string, string> $headers */
        $headers = config('security.headers');

        $response->headers->set('X-Frame-Options', $headers['x_frame_options']);
        $response->headers->set('X-Content-Type-Options', $headers['x_content_type_options']);
        $response->headers->set('Referrer-Policy', $headers['referrer_policy']);
        $response->headers->set('Permissions-Policy', $headers['permissions_policy']);
        $response->headers->set('Cross-Origin-Opener-Policy', $headers['cross_origin_opener_policy']);

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', $headers['hsts']);
        }

        if (config('security.csp.enabled')) {
            $policy = (new ContentSecurityPolicy(
                nonce: $nonce,
                local: app()->environment('local'),
                production: app()->isProduction(),
                viteOrigins: config('security.csp.vite_dev_origins'),
            ))->build();

            $name = config('security.csp.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($name, $policy);
        }

        return $response;
    }
}
