<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordSettingsController;
use App\Http\Controllers\Auth\RecoveryCodesController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorSettingsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

/*
| Trasy uwierzytelniania (ADR-006). Fortify ma wyłączone własne trasy (ignoreRoutes),
| a kontrolery Fortify podpinamy tutaj pod polskie adresy z zachowaniem standardowych nazw.
| Przy aktualizacji Fortify porównaj z vendor/laravel/fortify/routes/routes.php
| (test AuthRoutesTest sprawdza nazwy tras).
*/

// --- logowanie i wylogowanie
Route::middleware('guest')->group(function () {
    Route::get('/logowanie', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/logowanie', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['throttle:login', 'turnstile:after-failures'])
        ->name('login.store');

    // Wyzwanie 2FA po poprawnym haśle.
    Route::get('/logowanie/kod', [TwoFactorAuthenticatedSessionController::class, 'create'])
        ->middleware('two-factor.guard')
        ->name('two-factor.login');
    Route::post('/logowanie/kod', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->middleware(['two-factor.guard', 'throttle:two-factor'])
        ->name('two-factor.login.store');
});

Route::post('/wyloguj', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// --- rejestracja (własne kontrolery; użytkownik NIE jest logowany po rejestracji)
Route::middleware('guest')->prefix('rejestracja')->group(function () {
    Route::get('/', [RegisterController::class, 'choose'])->name('register');
    Route::get('/wlasciciel', [RegisterController::class, 'showOwner'])->name('register.owner');
    Route::get('/fachowiec', [RegisterController::class, 'showSpecialist'])->name('register.specialist');
    Route::post('/wlasciciel', [RegisterController::class, 'storeOwner'])
        ->middleware(['throttle:register', 'turnstile:always'])
        ->name('register.owner.store');
    Route::post('/fachowiec', [RegisterController::class, 'storeSpecialist'])
        ->middleware(['throttle:register', 'turnstile:always'])
        ->name('register.specialist.store');
    Route::get('/sprawdz-skrzynke', [RegisterController::class, 'sent'])->name('register.sent');
});

// --- reset hasła (odpowiedź neutralna; Turnstile tylko na prośbie o link, korekta K9)
Route::middleware('guest')->group(function () {
    Route::get('/nie-pamietam-hasla', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/nie-pamietam-hasla', [PasswordResetLinkController::class, 'store'])
        ->middleware(['throttle:password-reset', 'turnstile:always'])
        ->name('password.email');
    Route::get('/nowe-haslo/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/nowe-haslo', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

// --- weryfikacja e-mail
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/email/potwierdz', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/ponow', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification')
        ->name('verification.send');
    Route::get('/email/zmien', [EmailVerificationController::class, 'changeForm'])->name('email.change');
    Route::post('/email/zmien', [EmailVerificationController::class, 'change'])
        ->middleware('throttle:email-change')
        ->name('email.change.store');
});
// Link z maila działa bez sesji i nie loguje; podpis sprawdza kontroler (nieważny link -> ekran "nieważny").
Route::get('/email/potwierdz/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:6,1')
    ->name('verification.verify');
// Link z maila potwierdzający NOWY adres po zmianie (też bez sesji, nie loguje).
Route::get('/email/zmien/potwierdz/{id}/{hash}', [EmailVerificationController::class, 'verifyChange'])
    ->middleware('throttle:6,1')
    ->name('email.change.verify');
Route::get('/email/potwierdzono', [EmailVerificationController::class, 'done'])->name('verification.done');

// --- potwierdzenie hasła (przed wrażliwymi operacjami; okno 15 min, config auth.password_timeout)
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/potwierdz-haslo', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/potwierdz-haslo', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.confirm.store');
});

// --- bezpieczeństwo konta: te trasy leżą POZA bramką two-factor.required (inaczej pętla przekierowań)
Route::middleware(['auth', 'verified', 'no-store'])->prefix('konto')->group(function () {
    Route::get('/bezpieczenstwo', [TwoFactorSettingsController::class, 'show'])->name('account.security');

    Route::middleware('password.confirm')->prefix('bezpieczenstwo')->group(function () {
        Route::post('/2fa', [TwoFactorSettingsController::class, 'enable'])->name('two-factor.enable');
        Route::post('/2fa/potwierdz', [TwoFactorSettingsController::class, 'confirm'])
            ->middleware('throttle:two-factor-setup')
            ->name('two-factor.confirm');
        Route::delete('/2fa', [TwoFactorSettingsController::class, 'disable'])->name('two-factor.disable');
        Route::get('/kody-awaryjne', [RecoveryCodesController::class, 'show'])->name('two-factor.codes.show');
        Route::get('/kody-awaryjne.txt', [RecoveryCodesController::class, 'download'])->name('two-factor.codes.download');
        Route::post('/kody-awaryjne', [RecoveryCodesController::class, 'regenerate'])->name('two-factor.codes.regenerate');
    });

    Route::post('/przypomnienie-2fa', [TwoFactorSettingsController::class, 'dismissReminder'])
        ->name('two-factor.reminder.dismiss');
    Route::post('/haslo', [PasswordSettingsController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change');
});
