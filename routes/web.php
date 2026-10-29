<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AanbodController;
use App\Http\Controllers\Account\AanbodVerlengenController;
use App\Http\Controllers\Account\ConversationController;
use App\Http\Controllers\Account\DeleteAccountController;
use App\Http\Controllers\Account\ExportController;
use App\Http\Controllers\Account\InboxController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\StartConversationController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\NuTeStekkenController as ApiNuTeStekkenController;
use App\Http\Controllers\Api\PartnersController as ApiPartnersController;
use App\Http\Controllers\Api\ZoekenController as ApiZoekenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Beheer\DashboardController as BeheerDashboardController;
use App\Http\Controllers\Beheer\LoginController as BeheerLoginController;
use App\Http\Controllers\ContentPageController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PartnersController;
use App\Http\Controllers\StektipsController;
use App\Http\Controllers\ZoekenController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/initiatieven', PartnersController::class)->name('partners');
Route::redirect('/partners', '/initiatieven', 301);
Route::get('/partners/{path}', function (string $path) {
    return redirect('/initiatieven/'.$path, 301);
})->where('path', '.*');
Route::get('/stijlgids', [PageController::class, 'stijlgids'])->name('stijlgids');
Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');

Route::get('/hoe-werkt-het', [ContentPageController::class, 'hoeWerktHet'])->name('hoe-werkt-het');
Route::get('/hulpmiddelen', [ContentPageController::class, 'hulpmiddelen'])->name('hulpmiddelen');
Route::get('/over', [ContentPageController::class, 'over'])->name('over');
Route::get('/doe-mee', [ContentPageController::class, 'doeMee'])->name('doe-mee');
Route::get('/stektips', [StektipsController::class, 'index'])->name('stektips.index');
Route::get('/stektips/{slug}', [StektipsController::class, 'show'])->name('stektips.show');

Route::get('/aanbod', ZoekenController::class)->name('aanbod');
Route::get('/api/zoeken', ApiZoekenController::class)->name('api.zoeken');
Route::get('/api/partners', ApiPartnersController::class)->name('api.partners');
Route::get('/api/nu-te-stekken', ApiNuTeStekkenController::class)->name('api.nu-te-stekken');

Route::get('/cron', CronController::class)->name('cron');
Route::get('/account/aanbod-verlengen', AanbodVerlengenController::class)
    ->name('account.aanbod-verlengen');

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

Route::middleware(['auth', 'verified:account.bevestiging-nodig'])->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');

    Route::get('/account/profiel', [ProfileController::class, 'edit'])->name('account.profiel');
    Route::post('/account/profiel', [ProfileController::class, 'update']);

    Route::get('/account/aanbod', [AanbodController::class, 'index'])->name('account.aanbod');
    Route::post('/account/aanbod', [AanbodController::class, 'store']);
    Route::post('/account/aanbod/{aanbod}/verlengen', [AanbodController::class, 'renew'])
        ->name('account.aanbod.renew');

    Route::get('/account/inbox', InboxController::class)->name('account.inbox');
    Route::get('/account/bericht-starten', StartConversationController::class)
        ->name('account.bericht-starten');
    Route::get('/account/gesprek/{gesprek}', [ConversationController::class, 'show'])
        ->name('account.gesprek');
    Route::post('/account/gesprek/{gesprek}', [ConversationController::class, 'store'])
        ->name('account.gesprek.store');

    Route::get('/account/export', ExportController::class)->name('account.export');
    Route::get('/account/verwijderen', [DeleteAccountController::class, 'edit'])->name('account.verwijderen');
    Route::post('/account/verwijderen', [DeleteAccountController::class, 'destroy']);
});

Route::get('/beheer', [BeheerLoginController::class, 'create'])->name('beheer.login');
Route::post('/beheer', [BeheerLoginController::class, 'store'])
    ->middleware('throttle:5,1');
Route::post('/beheer/uitloggen', [BeheerLoginController::class, 'destroy'])->name('beheer.logout');

Route::middleware('admin')->prefix('beheer')->group(function () {
    Route::get('/overzicht', [BeheerDashboardController::class, 'index'])->name('beheer.index');
    Route::post('/markers', [BeheerDashboardController::class, 'saveMarker'])->name('beheer.markers.save');
    Route::post('/markers/verwijderen', [BeheerDashboardController::class, 'deleteMarker'])->name('beheer.markers.delete');
    Route::post('/meldingen/afgehandeld', [BeheerDashboardController::class, 'markReportHandled'])->name('beheer.reports.handled');
});
