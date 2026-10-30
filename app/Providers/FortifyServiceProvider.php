<?php

namespace App\Providers;

use App\Domain\Accounts\Actions\ResetUserPassword;
use App\Domain\Accounts\Support\LoginFailureCounter;
use App\Http\Responses\NeutralFailedPasswordResetResponse;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Konfiguracja Fortify (ADR-006): własne trasy w routes/auth.php, widoki, kontrakty, limitery.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Wyłącza domyślne trasy Fortify (mamy własne, polskie adresy).
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Widoki, akcje, neutralne odpowiedzi i limitery.
     */
    public function boot(): void
    {
        $this->registerViews();
        $this->registerContracts();
        $this->registerAuthentication();
        $this->registerRateLimiters();
    }

    /**
     * Nazwy widoków Blade dla ekranów obsługiwanych przez kontrolery Fortify.
     */
    private function registerViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $oldEmail = $request->old('email');

            return view('auth.login', [
                'requiresTurnstile' => app(LoginFailureCounter::class)->requiresTurnstile(
                    (string) $request->ip(),
                    is_string($oldEmail) ? $oldEmail : null,
                ),
            ]);
        });
        Fortify::requestPasswordResetLinkView('auth.forgot-password');
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]));
        Fortify::twoFactorChallengeView('auth.two-factor-challenge');
        Fortify::confirmPasswordView('auth.confirm-password');
    }

    /**
     * Podmiana domyślnych klas Fortify na własne.
     */
    private function registerContracts(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Reset hasła nie ujawnia istnienia kont (każdy wynik = ten sam komunikat).
        // Zły token resetu daje ten sam komunikat dla istniejącego i nieistniejącego adresu.
        $this->app->bind(FailedPasswordResetResponse::class, NeutralFailedPasswordResetResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);
    }

    /**
     * Logowanie: jednakowy błąd dla złego e-maila i złego hasła, blokada kont zbanowanych
     * ujawniana dopiero po poprawnym haśle.
     */
    private function registerAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = $request->input(Fortify::username());
            $password = $request->input('password');

            if (! is_string($email) || ! is_string($password)) {
                return null;
            }

            $user = User::query()->where('email', Str::lower(trim($email)))->first();

            // Dla nieistniejącego konta też liczymy skrót hasła, żeby czas odpowiedzi był podobny.
            $hash = $user !== null ? $user->password : Hash::make('wyrownanie-czasu-odpowiedzi');
            $passwordOk = Hash::check($password, $hash);

            // Baza (collation unicode_ci) uznaje "jó@x.pl" za "jo@x.pl"; logujemy tylko dokładnym adresem konta,
            // żeby warianty zapisu nie omijały limitów (liczniki i tak używają postaci kanonicznej).
            if ($user === null || ! $passwordOk || $user->email !== Str::lower(trim($email))) {
                return null;
            }

            if ($user->isBanned()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('auth.login.blocked', ['email' => config('app.contact_email')]),
                ]);
            }

            return $user;
        });
    }

    /**
     * Limity żądań (tabela w zadaniu 002, §6). Klucze nie zawierają danych wrażliwych.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($this->emailKey($request).'|'.$request->ip()),
            Limit::perHour(30)->by('login-ip:'.$request->ip()),
        ]);

        // Limity rejestracji i resetu hasła liczone TYLKO po IP. Limit po adresie e-mail pozwalałby
        // napastnikowi zablokować cudzą rejestrację lub reset (3 żądania na godzinę z adresem ofiary).
        // Liczbę wysyłanych maili na adres ogranicza się w akcjach (RateLimiter::attempt), bez błędu dla użytkownika.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perHour(10)->by('register-ip:'.$request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perHour(5)->by('reset-ip:'.$request->ip()),
        ]);

        RateLimiter::for('verification', fn (Request $request) => [
            Limit::perMinute(1)->by('verify-user:'.$request->user()?->getAuthIdentifier()),
            Limit::perHour(6)->by('verify-user-hour:'.$request->user()?->getAuthIdentifier()),
        ]);

        RateLimiter::for('email-change', fn (Request $request) => Limit::perHour(3)
            ->by('email-change:'.$request->user()?->getAuthIdentifier()));

        // Zgrubny limit przeciwko floodowi; właściwa blokada (5 błędów -> 15 min) jest liczona osobno.
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinutes(15, 20)
            ->by('two-factor-ip:'.$request->ip()));

        RateLimiter::for('two-factor-setup', fn (Request $request) => Limit::perMinutes(15, 10)
            ->by('two-factor-setup:'.$request->user()?->getAuthIdentifier()));
    }

    /**
     * Klucz limitera z adresu e-mail (małe litery, bez znaków spoza ASCII).
     */
    private function emailKey(Request $request): string
    {
        $email = $request->input('email');

        // Tablica lub inny typ zamiast tekstu: walidacja zwróci błąd 422, limiter nie może rzucić wyjątku.
        return is_string($email) ? EmailNormalizer::canonical($email) : '';
    }
}
