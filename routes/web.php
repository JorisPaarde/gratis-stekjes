<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AanbodController;
use App\Http\Controllers\Account\AanbodVerlengenController;
use App\Http\Controllers\Account\ConversationController;
use App\Http\Controllers\Account\DeleteAccountController;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Controllers\Account\ErvaringController as AccountErvaringController;
use App\Http\Controllers\Account\ExportController;
use App\Http\Controllers\Account\InboxController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\StartConversationController;
use App\Http\Controllers\Account\VerenigingController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\DeelplekkenController as ApiDeelplekkenController;
use App\Http\Controllers\Api\NuTeStekkenController as ApiNuTeStekkenController;
use App\Http\Controllers\Api\PartnersController as ApiPartnersController;
use App\Http\Controllers\Api\PlantensoortenController as ApiPlantensoortenController;
use App\Http\Controllers\Api\ZoekenController as ApiZoekenController;
use App\Http\Controllers\AudienceLandingController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BackupCronController;
use App\Http\Controllers\Beheer\DashboardController as BeheerDashboardController;
use App\Http\Controllers\Beheer\ErvaringController as BeheerErvaringController;
use App\Http\Controllers\Beheer\FeedbackController as BeheerFeedbackController;
use App\Http\Controllers\Beheer\LoginController as BeheerLoginController;
use App\Http\Controllers\Beheer\StatistiekenController as BeheerStatistiekenController;
use App\Http\Controllers\BerichtmailController;
use App\Http\Controllers\ContentPageController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DeelplekkenController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\DeployImportController;
use App\Http\Controllers\ErvaringController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GratisPlantenController;
use App\Http\Controllers\InvasieveSoortenController;
use App\Http\Controllers\Media\AanbodFotoController;
use App\Http\Controllers\OrganisatieController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PartnersController;
use App\Http\Controllers\StektipsController;
use App\Http\Controllers\VoorOrganisatiesController;
use App\Http\Controllers\ZoekenController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/ervaringen', [ErvaringController::class, 'index'])->name('ervaringen.index');
Route::get('/ervaringen/{ervaring}/foto', [ErvaringController::class, 'photo'])
    ->whereNumber('ervaring')
    ->name('ervaringen.foto');
Route::get('/initiatieven', PartnersController::class)->name('partners');
Route::get('/deelplekken', DeelplekkenController::class)->name('deelplekken');
Route::get('/voor-organisaties', [VoorOrganisatiesController::class, 'index'])->name('voor-organisaties');
Route::get('/voor-organisaties/aanmelden', [VoorOrganisatiesController::class, 'create'])
    ->name('voor-organisaties.aanmelden');
Route::post('/voor-organisaties/aanmelden', [VoorOrganisatiesController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('voor-organisaties.aanmelden.store');
Route::get('/organisaties', [OrganisatieController::class, 'index'])->name('organisaties.index');
Route::redirect('/organisaties/plant-voor-plant', '/organisaties', 301);
Route::get('/organisaties/{slug}/poster', [OrganisatieController::class, 'poster'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('organisaties.poster');
Route::get('/organisaties/{slug}', [OrganisatieController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('organisaties.show');
Route::redirect('/partners', '/initiatieven', 301);
Route::get('/partners/{path}', function (string $path) {
    return redirect('/initiatieven/'.$path, 301);
})->where('path', '.*');
// Stijlgids: nooit publiek op de site, alleen na beheer-login (gast → /beheer).
Route::get('/stijlgids', [PageController::class, 'stijlgids'])->middleware('admin')->name('stijlgids');
Route::get('/robots.txt', [PageController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('/manifest.webmanifest', [PageController::class, 'manifest'])->name('manifest');

Route::get('/hoe-werkt-het', [ContentPageController::class, 'hoeWerktHet'])->name('hoe-werkt-het');
Route::get('/hulpmiddelen', [ContentPageController::class, 'hulpmiddelen'])->name('hulpmiddelen');
Route::get('/over', [ContentPageController::class, 'over'])->name('over');
Route::get('/doe-mee', [ContentPageController::class, 'doeMee'])->name('doe-mee');

Route::get('/volkstuinen', [AudienceLandingController::class, 'volkstuinen'])->name('volkstuinen');
Route::get('/help-mee', [AudienceLandingController::class, 'helpMee'])->name('help-mee');
Route::get('/natuur', [AudienceLandingController::class, 'natuur'])->name('natuur');
Route::get('/verbinding', [AudienceLandingController::class, 'verbinding'])->name('verbinding');
Route::get('/invasieve-soorten', InvasieveSoortenController::class)->name('invasieve-soorten');
Route::get('/stektips', [StektipsController::class, 'index'])->name('stektips.index');
Route::get('/stektips/artikel/{slug}', [StektipsController::class, 'artikel'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('stektips.artikel');
Route::get('/stektips/{slug}', [StektipsController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('stektips.show');

Route::get('/gratis-planten', [GratisPlantenController::class, 'index'])->name('gratis-planten.index');
Route::get('/gratis-planten/{stad}', [GratisPlantenController::class, 'show'])
    ->where('stad', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('gratis-planten.show');

Route::get('/aanbod', ZoekenController::class)->name('aanbod');
Route::get('/media/aanbod-foto/{foto}/{variant?}', AanbodFotoController::class)
    ->where(['foto' => '[0-9]+', 'variant' => 'full|thumb'])
    ->middleware('throttle:120,1')
    ->name('media.aanbod-foto');
Route::get('/api/zoeken', ApiZoekenController::class)->name('api.zoeken');
Route::get('/api/partners', ApiPartnersController::class)->name('api.partners');
Route::get('/api/deelplekken', ApiDeelplekkenController::class)->name('api.deelplekken');
Route::get('/api/nu-te-stekken', ApiNuTeStekkenController::class)->name('api.nu-te-stekken');
Route::get('/api/plantensoorten', ApiPlantensoortenController::class)
    ->middleware('throttle:plantensoorten-zoek')
    ->name('api.plantensoorten');

Route::get('/cron', CronController::class)->name('cron');
Route::get('/cron/backup', BackupCronController::class)
    ->middleware('throttle:10,1')
    ->name('cron.backup');
Route::post('/deploy/run', DeployController::class)
    ->middleware('throttle:10,1')
    ->name('deploy.run');
Route::post('/deploy/import/init', [DeployImportController::class, 'init'])
    ->middleware('throttle:20,1')
    ->name('deploy.import.init');
Route::post('/deploy/import/chunk', [DeployImportController::class, 'chunk'])
    ->middleware('throttle:60,1')
    ->name('deploy.import.chunk');
Route::post('/deploy/import', [DeployImportController::class, 'import'])
    ->middleware('throttle:10,1')
    ->name('deploy.import');
Route::get('/account/aanbod-verlengen', AanbodVerlengenController::class)
    ->name('account.aanbod-verlengen');

Route::middleware('guest')->group(function () {
    Route::get('/account/registreren', [RegisterController::class, 'create'])->name('account.registreren');
    Route::post('/account/registreren', [RegisterController::class, 'store']);

    Route::get('/account/inloggen', [LoginController::class, 'create'])->name('account.inloggen');
    Route::post('/account/inloggen', [LoginController::class, 'store']);

    Route::get('/account/wachtwoord-vergeten', [ForgotPasswordController::class, 'create'])
        ->name('account.wachtwoord.vergeten');
    Route::post('/account/wachtwoord-vergeten', [ForgotPasswordController::class, 'store']);

    Route::get('/account/wachtwoord-reset/{token}', [ResetPasswordController::class, 'create'])
        ->name('account.wachtwoord.reset');
    Route::post('/account/wachtwoord-reset', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('account.wachtwoord.reset.store');
});

Route::get('/account/bevestiging-nodig', [VerifyEmailController::class, 'notice'])
    ->name('account.bevestiging-nodig');
Route::post('/account/bevestiging-opnieuw', [VerifyEmailController::class, 'resend'])
    ->name('account.bevestiging-opnieuw');
Route::get('/account/bevestigen/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('account.bevestigen');

Route::get('/account/email-bevestigen/{id}/{hash}', [EmailChangeController::class, 'confirm'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('account.email.bevestigen');

Route::post('/account/uitloggen', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('account.uitloggen');

Route::middleware(['auth', 'verified:account.bevestiging-nodig'])->group(function () {
    Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');

    Route::get('/account/profiel', [ProfileController::class, 'edit'])->name('account.profiel');
    Route::post('/account/profiel', [ProfileController::class, 'update']);
    Route::get('/account/adres-zoeken', [ProfileController::class, 'adresZoeken'])
        ->middleware('throttle:30,1')
        ->name('account.adres-zoeken');

    Route::get('/account/wachtwoord', [PasswordController::class, 'edit'])->name('account.wachtwoord');
    Route::post('/account/wachtwoord', [PasswordController::class, 'update']);

    Route::get('/account/email', [EmailChangeController::class, 'edit'])->name('account.email');
    Route::post('/account/email', [EmailChangeController::class, 'update']);
    Route::post('/account/email/annuleren', [EmailChangeController::class, 'cancel'])
        ->name('account.email.annuleren');

    Route::get('/account/aanbod', [AanbodController::class, 'index'])->name('account.aanbod');
    Route::post('/account/aanbod/nieuw', [AanbodController::class, 'store'])
        ->name('account.aanbod.store');
    Route::post('/account/aanbod/{aanbod}/verlengen', [AanbodController::class, 'renew'])
        ->name('account.aanbod.renew');
    Route::delete('/account/aanbod/{aanbod}', [AanbodController::class, 'destroy'])
        ->name('account.aanbod.destroy');
    Route::post('/account/aanbod/{aanbod}', [AanbodController::class, 'update'])
        ->name('account.aanbod.update');
    // Oude POST /account/aanbod (vóór /nieuw) → zelfde store-action.
    Route::post('/account/aanbod', [AanbodController::class, 'store']);

    Route::get('/account/ervaringen', [AccountErvaringController::class, 'index'])
        ->name('account.ervaringen');
    Route::get('/account/ervaringen/nieuw', [AccountErvaringController::class, 'create'])
        ->name('account.ervaringen.create');
    Route::post('/account/ervaringen/nieuw', [AccountErvaringController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('account.ervaringen.store');
    Route::delete('/account/ervaringen/{ervaring}', [AccountErvaringController::class, 'destroy'])
        ->name('account.ervaringen.destroy');
    Route::get('/account/ervaringen/{ervaring}/foto', [AccountErvaringController::class, 'photo'])
        ->whereNumber('ervaring')
        ->name('account.ervaringen.foto');

    Route::get('/account/verenigingen', [VerenigingController::class, 'index'])->name('account.verenigingen');
    Route::post('/account/verenigingen', [VerenigingController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('account.verenigingen.store');
    Route::post('/account/verenigingen/{vereniging}', [VerenigingController::class, 'update'])
        ->whereNumber('vereniging')
        ->name('account.verenigingen.update');
    Route::delete('/account/verenigingen/{vereniging}', [VerenigingController::class, 'destroy'])
        ->whereNumber('vereniging')
        ->name('account.verenigingen.destroy');

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
    Route::get('/statistieken', [BeheerStatistiekenController::class, 'index'])->name('beheer.statistieken');
    Route::get('/feedback', [BeheerFeedbackController::class, 'index'])->name('beheer.feedback');
    Route::post('/feedback/gelezen', [BeheerFeedbackController::class, 'markRead'])->name('beheer.feedback.read');
    Route::delete('/feedback/{feedback}', [BeheerFeedbackController::class, 'destroy'])
        ->whereNumber('feedback')
        ->name('beheer.feedback.destroy');
    Route::get('/ervaringen', [BeheerErvaringController::class, 'index'])->name('beheer.ervaringen');
    Route::post('/ervaringen/goedkeuren', [BeheerErvaringController::class, 'approve'])
        ->name('beheer.ervaringen.approve');
    Route::post('/ervaringen/afwijzen', [BeheerErvaringController::class, 'reject'])
        ->name('beheer.ervaringen.reject');
    Route::post('/ervaringen/verwijderen', [BeheerErvaringController::class, 'destroy'])
        ->name('beheer.ervaringen.destroy');
    Route::get('/ervaringen/{ervaring}/foto', [BeheerErvaringController::class, 'photo'])
        ->whereNumber('ervaring')
        ->name('beheer.ervaringen.foto');
    Route::post('/markers', [BeheerDashboardController::class, 'saveMarker'])->name('beheer.markers.save');
    Route::post('/markers/verwijderen', [BeheerDashboardController::class, 'deleteMarker'])->name('beheer.markers.delete');
    Route::post('/evenementen', [BeheerDashboardController::class, 'saveEvenement'])->name('beheer.evenementen.save');
    Route::post('/evenementen/verbergen', [BeheerDashboardController::class, 'hideEvenement'])->name('beheer.evenementen.hide');
    Route::post('/meldingen/afgehandeld', [BeheerDashboardController::class, 'markReportHandled'])->name('beheer.reports.handled');
    Route::post('/deelplekken/goedkeuren', [BeheerDashboardController::class, 'approveDeelplek'])
        ->name('beheer.deelplekken.approve');
    Route::post('/deelplekken/afwijzen', [BeheerDashboardController::class, 'rejectDeelplek'])
        ->name('beheer.deelplekken.reject');
    Route::post('/gebruikers/bevestiging-opnieuw', [BeheerDashboardController::class, 'resendVerification'])
        ->middleware('throttle:10,1')
        ->name('beheer.users.resend-verification');
    Route::post('/verenigingen/{vereniging}/status', [BeheerDashboardController::class, 'setVerenigingStatus'])
        ->middleware('throttle:30,1')
        ->whereNumber('vereniging')
        ->name('beheer.verenigingen.status');
    Route::post('/gebruikers/{user}/pionier', [BeheerDashboardController::class, 'setPionier'])
        ->middleware('throttle:10,1')
        ->whereNumber('user')
        ->name('beheer.users.pionier');
    Route::get('/gebruikers/{user}/verwijderen', [BeheerDashboardController::class, 'confirmDestroyUser'])
        ->whereNumber('user')
        ->name('beheer.users.destroy.confirm');
    Route::post('/gebruikers/{user}/verwijderen', [BeheerDashboardController::class, 'destroyUser'])
        ->middleware('throttle:10,1')
        ->whereNumber('user')
        ->name('beheer.users.destroy');
});

// Mail bij nieuwe berichten uitzetten via ondertekende link (zonder inloggen).
Route::get('/berichtmail/uit/{user}', [BerichtmailController::class, 'show'])
    ->middleware(['signed', 'throttle:20,1'])
    ->whereNumber('user')
    ->name('berichtmail.uit');
Route::post('/berichtmail/uit/{user}', [BerichtmailController::class, 'update'])
    ->middleware(['signed', 'throttle:20,1'])
    ->whereNumber('user');
