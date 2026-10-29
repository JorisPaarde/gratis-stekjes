<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');

Route::middleware('guest')->group(function () {
    Route::get('/account/registreren', [RegisterController::class, 'create'])->name('account.registreren');
    Route::post('/account/registreren', [RegisterController::class, 'store']);

    Route::get('/account/inloggen', [LoginController::class, 'create'])->name('account.inloggen');
    Route::post('/account/inloggen', [LoginController::class, 'store']);
});

Route::get('/account/bevestiging-nodig', [VerifyEmailController::class, 'notice'])
    ->name('account.bevestiging-nodig');
Route::post('/account/bevestiging-opnieuw', [VerifyEmailController::class, 'resend'])
    ->middleware('throttle:6,1')
    ->name('account.bevestiging-opnieuw');
Route::get('/account/bevestigen/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('account.bevestigen');

Route::post('/account/uitloggen', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('account.uitloggen');

Route::get('/account', [AccountController::class, 'index'])
    ->middleware(['auth', 'verified:account.bevestiging-nodig'])
    ->name('account.index');
