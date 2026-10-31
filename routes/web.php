<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocalitySearchController;
use App\Http\Middleware\EnsureAccountIsNotBanned;
use App\Http\Middleware\RejectRememberedPrivilegedSessions;
use App\Http\Middleware\RejectRevokedSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::view('/', 'welcome')->name('home');

require __DIR__.'/auth.php';

// Pulpit: kolejność bramek auth -> verified -> two-factor.required (ADR-009).
Route::get('/pulpit', DashboardController::class)
    ->middleware(['auth', 'verified', 'two-factor.required', 'no-store'])
    ->name('dashboard');

// Strony statyczne (szkice, DO WERYFIKACJI PRZEZ PRAWNIKA); treść w lang/pl/pages.php.
Route::view('/regulamin', 'pages.terms')->name('terms');
Route::view('/polityka-prywatnosci', 'pages.privacy')->name('privacy');
Route::view('/polityka-cookies', 'pages.cookies')->name('cookies');
Route::view('/kontakt', 'pages.contact')->name('contact');

// Autouzupełnianie miejscowości (TERYT): publiczne, tylko GET, JSON z nagłówkiem Cache-Control: public.
// Bez sesji i ciasteczek (odpowiedź nie może ustawiać Set-Cookie, bo ma być współdzielona w cache);
// limit żądań na IP w AppServiceProvider (limiter "locality-search").
Route::get('/miejscowosci/szukaj', LocalitySearchController::class)
    ->middleware('throttle:locality-search')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
        AuthenticateSession::class,
        RejectRevokedSessions::class,
        RejectRememberedPrivilegedSessions::class,
        EnsureAccountIsNotBanned::class,
    ])
    ->name('localities.search');
