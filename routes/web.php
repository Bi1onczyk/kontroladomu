<?php

use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocalitySearchController;
use App\Http\Controllers\PublicSpecialistController;
use App\Http\Controllers\Specialist\CertificateController;
use App\Http\Controllers\Specialist\CertificateFileController;
use App\Http\Controllers\Specialist\ProfileController;
use App\Http\Controllers\Specialist\PublicationController;
use App\Http\Controllers\Specialist\ServiceAreaController;
use App\Http\Controllers\Specialist\ServicesController;
use App\Http\Controllers\Specialist\SlugController;
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
// Opis weryfikacji fachowców (szkic, DO WERYFIKACJI PRZEZ PRAWNIKA); treść pisze UX/frontend.
Route::view('/jak-weryfikujemy-fachowcow', 'pages.verification')->name('pages.verification');

// Publiczna strona profilu fachowca (zadanie 004). Slug sprawdza kontroler (obsługa przekierowań 301 ze starych adresów),
// widoczność rozstrzyga SpecialistProfilePolicy@view. Limit żądań na IP: profile-public (AppServiceProvider).
Route::get('/fachowiec/{slug}', [PublicSpecialistController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->middleware('throttle:profile-public')
    ->name('specialists.show');

// Panel fachowca (zadanie 004): kolejność bramek jak w ADR-009, potem Gate `manage-profile` (rola `specialist`).
// Profil jest zawsze profilem zalogowanego użytkownika (brak ID profilu w adresach); prawa do zaświadczeń sprawdzają Policies.
Route::middleware(['auth', 'verified', 'two-factor.required', 'no-store', 'can:manage-profile'])
    ->prefix('panel')
    ->group(function (): void {
        Route::get('/profil', [ProfileController::class, 'edit'])->name('specialist.profile.edit');

        Route::middleware('throttle:profile-update')->group(function (): void {
            Route::put('/profil', [ProfileController::class, 'update'])->name('specialist.profile.update');
            Route::put('/profil/adres', [SlugController::class, 'update'])->name('specialist.profile.slug');
            Route::put('/profil/uslugi', [ServicesController::class, 'update'])->name('specialist.profile.services');
            Route::put('/profil/obszar', [ServiceAreaController::class, 'update'])->name('specialist.profile.area');
            Route::post('/profil/publikacja', [PublicationController::class, 'store'])->name('specialist.profile.publish');
            Route::delete('/profil/publikacja', [PublicationController::class, 'destroy'])->name('specialist.profile.unpublish');
        });

        Route::post('/profil/obszar/ponow', [ServiceAreaController::class, 'retry'])
            ->middleware('throttle:geocode-retry')
            ->name('specialist.profile.area.retry');

        Route::get('/zaswiadczenia', [CertificateController::class, 'index'])
            ->middleware('can:viewAny,'.SpecialistCertificate::class)
            ->name('specialist.certificates.index');
        Route::post('/zaswiadczenia', [CertificateController::class, 'store'])
            ->middleware(['can:create,'.SpecialistCertificate::class, 'throttle:certificate-upload'])
            ->name('specialist.certificates.store');
        Route::put('/zaswiadczenia/{certificate:ulid}', [CertificateController::class, 'update'])
            ->middleware('throttle:profile-update')
            ->name('specialist.certificates.update');
        Route::delete('/zaswiadczenia/{certificate:ulid}', [CertificateController::class, 'destroy'])
            ->middleware('throttle:profile-update')
            ->name('specialist.certificates.destroy');
    });

// Pobranie pliku zaświadczenia: poza bramką `manage-profile`, bo plik może pobrać także administrator.
// Decyzję podejmuje SpecialistCertificatePolicy@view (właściciel profilu lub admin); inni dostają 404 (jak przy braku pliku).
Route::get('/panel/zaswiadczenia/{certificate:ulid}/plik', [CertificateFileController::class, 'show'])
    ->middleware(['auth', 'verified', 'two-factor.required', 'no-store', 'throttle:60,1'])
    ->name('specialist.certificates.file');

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
