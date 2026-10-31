<?php

namespace App\Providers;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Listeners\ClearLoginFailures;
use App\Domain\Accounts\Listeners\CountLoginFailure;
use App\Domain\Accounts\Listeners\MarkPasswordConfirmedOnLogin;
use App\Domain\Accounts\Listeners\RecordAuthenticationActivity;
use App\Domain\Accounts\Listeners\RecordTwoFactorActivity;
use App\Domain\Accounts\Listeners\TwoFactorChallengeListeners;
use App\Domain\Geo\Contracts\GeocodingProvider;
use App\Domain\Geo\Services\NominatimProvider;
use App\Models\User;
use App\Support\Security\ProductionConfigGuard;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Dostawca geokodowania (ADR-013); podmiana na innego dostawcę = zmiana tej jednej linii.
        $this->app->bind(GeocodingProvider::class, NominatimProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardProductionConfig();
        $this->registerPasswordRules();
        $this->registerEventListeners();
        $this->registerRateLimiters();
        $this->registerGates();
    }

    /**
     * W każdym środowisku poza local/testing aplikacja nie startuje z konfiguracją deweloperską (debug, klucze testowe Turnstile,
     * kolejka sync, mailer log, ciasteczko bez Secure, wyłączone sprawdzanie haseł; CLAUDE.md §8).
     * Linki w mailach i adresy URL są zawsze budowane z APP_URL po https (odporność na podmianę nagłówka Host).
     */
    private function guardProductionConfig(): void
    {
        if ($this->app->environment('local', 'testing')) {
            return;
        }

        // Komendy przygotowujące wdrożenie (composer install, cache konfiguracji) działają przed ustawieniem .env.
        $command = $_SERVER['argv'][1] ?? '';
        $allowed = ['package:discover', 'key:generate', 'config:cache', 'config:clear', 'cache:clear', 'optimize', 'optimize:clear', 'storage:link'];

        if (! ($this->app->runningInConsole() && in_array($command, $allowed, true))) {
            $violations = (new ProductionConfigGuard)->violations();

            if ($violations !== []) {
                throw new RuntimeException('Niebezpieczna konfiguracja produkcyjna: '.implode(' ', $violations));
            }
        }

        URL::forceRootUrl((string) config('app.url'));
        URL::forceScheme('https');
    }

    /**
     * Domyślna reguła haseł: min. 12 znaków; sprawdzenie wycieku (HIBP) wyłączane w testach.
     */
    private function registerPasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12);

            return config('auth.check_pwned') ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Listenery zdarzeń uwierzytelniania (dziennik, liczniki, potwierdzenie hasła w sesji).
     */
    private function registerEventListeners(): void
    {
        Event::listen(Login::class, [RecordAuthenticationActivity::class, 'handleLogin']);
        Event::listen(Login::class, MarkPasswordConfirmedOnLogin::class);
        Event::listen(Login::class, ClearLoginFailures::class);
        Event::listen(Logout::class, [RecordAuthenticationActivity::class, 'handleLogout']);
        Event::listen(Failed::class, CountLoginFailure::class);

        // 2FA: zegar wyzwania (10 min), licznik błędnych kodów (5 -> 15 min), dziennik.
        Event::listen(TwoFactorAuthenticationChallenged::class, [TwoFactorChallengeListeners::class, 'started']);
        Event::listen(TwoFactorAuthenticationFailed::class, [TwoFactorChallengeListeners::class, 'failed']);
        Event::listen(RecoveryCodeReplaced::class, [TwoFactorChallengeListeners::class, 'recoveryCodeUsed']);
        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, [TwoFactorChallengeListeners::class, 'succeeded']);
        Event::listen(RecordTwoFactorActivity::events(), RecordTwoFactorActivity::class);
    }

    /**
     * Limity żądań. `locality-search`: autouzupełnianie miejscowości, 60 żądań/min i 8 żądań/s na adres IP
     * (pisanie z opóźnieniem 250 ms daje krótkie serie kilku żądań na sekundę). Po przekroczeniu: 429 z JSON-em
     * i nagłówkiem Retry-After.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('locality-search', function (Request $request): array {
            $tooMany = fn (Request $request, array $headers) => response()
                ->json(['message' => __('geo.rate_limited')], 429, $headers);

            return [
                Limit::perMinute(60)->by($request->ip())->response($tooMany),
                Limit::perSecond(8)->by($request->ip())->response($tooMany),
            ];
        });

        // Zadanie 004. Zapisy profilu: 30/min na użytkownika. Wysyłanie zaświadczeń: 10/godz. na użytkownika.
        // Ponowienie geokodowania: 3/godz. na użytkownika. Strona publiczna profilu: 120/min na adres IP.
        RateLimiter::for('profile-update', fn (Request $request): Limit => Limit::perMinute(30)->by('profile-update:'.(Auth::id() ?? $request->ip())));
        RateLimiter::for('certificate-upload', fn (Request $request): Limit => Limit::perHour(10)->by('certificate-upload:'.(Auth::id() ?? $request->ip())));
        RateLimiter::for('geocode-retry', fn (Request $request): Limit => Limit::perHour(3)->by('geocode-retry:'.(Auth::id() ?? $request->ip())));
        RateLimiter::for('profile-public', fn (Request $request): Limit => Limit::perMinute(120)->by('profile-public:'.$request->ip()));
    }

    /**
     * Bramka panelu fachowca (zadanie 004): dostęp do tras `/panel/profil*` i `/panel/zaswiadczenia*`
     * ma tylko konto z rolą `specialist` (pracownik `technician` i administrator nie).
     * Szczegółowe prawa do profilu i zaświadczeń sprawdzają Policies.
     */
    private function registerGates(): void
    {
        Gate::define('manage-profile', fn (User $user): bool => $user->hasRole(UserRole::Specialist->value));
    }
}
