<?php

namespace App\Providers;

use App\Domain\Accounts\Listeners\ClearLoginFailures;
use App\Domain\Accounts\Listeners\CountLoginFailure;
use App\Domain\Accounts\Listeners\MarkPasswordConfirmedOnLogin;
use App\Domain\Accounts\Listeners\RecordAuthenticationActivity;
use App\Domain\Accounts\Listeners\RecordTwoFactorActivity;
use App\Domain\Accounts\Listeners\TwoFactorChallengeListeners;
use App\Support\Security\ProductionConfigGuard;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardProductionConfig();
        $this->registerPasswordRules();
        $this->registerEventListeners();
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
}
