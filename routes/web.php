<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

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
