<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Organisaties;
use App\Services\Steden;
use App\Services\Stektips;
use Database\Seeders\DeelplekkenSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Crawl-achtige statuscontrole: elke publieke + ingelogde route moet < 500 geven
 * (leeg: alleen deelplekken-seed; gevuld: demo-aanbod/gebruikers/berichten).
 */
function auditPublicGetRoutes(): array
{
    $routes = [
        '/',
        '/privacy',
        '/initiatieven',
        '/deelplekken',
        '/voor-organisaties',
        '/voor-organisaties/aanmelden',
        '/organisaties',
        '/stijlgids',
        '/robots.txt',
        '/sitemap.xml',
        '/hoe-werkt-het',
        '/hulpmiddelen',
        '/over',
        '/invasieve-soorten',
        '/stektips',
        '/gratis-planten',
        '/aanbod',
        '/api/zoeken?q=Haarlem',
        '/api/partners',
        '/api/deelplekken',
        '/api/nu-te-stekken',
        '/account/registreren',
        '/account/inloggen',
        '/account/wachtwoord-vergeten',
        '/beheer',
        '/partners', // 301
    ];

    foreach (app(Stektips::class)->all() as $soort) {
        $routes[] = '/stektips/'.$soort['slug'];
    }
    foreach (app(Steden::class)->all() as $stad) {
        $routes[] = '/gratis-planten/'.$stad['slug'];
    }
    foreach (app(Organisaties::class)->all() as $org) {
        $routes[] = '/organisaties/'.$org['slug'];
    }

    return $routes;
}

function assertRouteStatusUnder500($test, string $path, int $max = 499): int
{
    $response = $test->get($path);
    $status = $response->getStatusCode();
    expect($status)->toBeLessThanOrEqual($max, "Route {$path} gaf HTTP {$status}");

    return $status;
}

test('route-audit leeg: alle publieke routes < 500', function () {
    Artisan::call('db:seed', ['--class' => DeelplekkenSeeder::class]);

    foreach (auditPublicGetRoutes() as $path) {
        assertRouteStatusUnder500($this, $path);
    }

    // Verwachte 404
    $this->get('/stektips/onbestaand-slug-xyz')->assertNotFound();
    $this->get('/gratis-planten/onbestaande-stad-xyz')->assertNotFound();
    $this->get('/organisaties/onbestaande-org-xyz')->assertNotFound();
});

test('route-audit gevuld: publiek + account + beheer < 500', function () {
    Artisan::call('db:seed', ['--class' => DeelplekkenSeeder::class]);
    Artisan::call('db:seed', ['--class' => DemoSeeder::class]);

    foreach (auditPublicGetRoutes() as $path) {
        assertRouteStatusUnder500($this, $path);
    }

    $user = User::query()->where('email', 'demo1@example.com')->first();
    expect($user)->not->toBeNull();

    $accountRoutes = [
        '/account',
        '/account/profiel',
        '/account/wachtwoord',
        '/account/email',
        '/account/aanbod',
        '/account/inbox',
        '/account/export',
        '/account/verwijderen',
    ];

    foreach ($accountRoutes as $path) {
        $status = $this->actingAs($user)->get($path)->getStatusCode();
        expect($status)->toBeLessThanOrEqual(499, "Account-route {$path} gaf HTTP {$status}");
    }

    $conv = Conversation::query()->first();
    expect($conv)->not->toBeNull();
    $this->actingAs($user)
        ->get(route('account.gesprek', $conv))
        ->assertOk();

    $aanbod = Aanbod::query()->where('user_id', '!=', $user->id)->publiclyListed()->first();
    expect($aanbod)->not->toBeNull();
    $this->actingAs($user)
        ->get(route('account.bericht-starten', [
            'aanbod_id' => $aanbod->id,
            'user_id' => $aanbod->user_id,
        ]))
        ->assertRedirect();

    config(['stekjes.admin_password' => 'route-audit-admin-pass']);
    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk();

    expect(Message::query()->count())->toBeGreaterThan(0);
    expect(Aanbod::query()->count())->toBeGreaterThan(0);
});

test('organisaties/{slug} blijft < 500 met config:cache + route:cache en APP_DEBUG=false', function () {
    // Bestand-sqlite i.p.v. :memory:, anders verdwijnt het schema bij config:cache / nieuwe PDO.
    $dbPath = database_path('route-audit-cache-test.sqlite');
    if (is_file($dbPath)) {
        unlink($dbPath);
    }
    touch($dbPath);

    $prevDebug = (bool) config('app.debug');
    config([
        'app.debug' => false,
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $dbPath,
    ]);
    putenv('APP_DEBUG=false');
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE='.$dbPath);
    $_ENV['APP_DEBUG'] = 'false';
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = $dbPath;
    $_SERVER['APP_DEBUG'] = 'false';
    $_SERVER['DB_CONNECTION'] = 'sqlite';
    $_SERVER['DB_DATABASE'] = $dbPath;

    try {
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->artisan('config:cache')->assertSuccessful();
        $this->artisan('route:cache')->assertSuccessful();
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(DeelplekkenSeeder::class);

        $orgs = app(Organisaties::class)->all();
        expect($orgs)->not->toBeEmpty();
        expect(collect($orgs)->pluck('slug'))->toContain('velt');

        foreach ($orgs as $org) {
            $path = '/organisaties/'.$org['slug'];
            $status = $this->get($path)->getStatusCode();
            expect($status)->toBeLessThan(500, "Gecachte route {$path} gaf HTTP {$status} (APP_DEBUG=false)");
            expect($status)->toBe(200);
        }

        $this->get('/organisaties/onbestaande-org-xyz')->assertNotFound();
        $this->get('/voor-organisaties')->assertOk();
        $this->get('/organisaties')->assertOk();
    } finally {
        $this->artisan('config:clear');
        $this->artisan('route:clear');
        config(['app.debug' => $prevDebug]);
        if (is_file($dbPath)) {
            unlink($dbPath);
        }
    }
});

test('stektips verbergt lege ervaringen/bronnen en linkt proef naar herfstolijfwilg', function () {
    $index = $this->get(route('stektips.index'))->assertOk();
    $index->assertSee(route('stektips.show', ['slug' => 'herfstolijfwilg']), false);
    $index->assertSee('Joris\' eigen proef', false);

    $html = $this->get(route('stektips.show', ['slug' => 'monstera']))
        ->assertOk()
        ->getContent();
    expect($html)->not->toContain('Ervaringen deelnemers');
    // monstera heeft geen bron uit nu-te-stekken → rij verborgen
    expect($html)->not->toContain('>Bronnen<');

    $olijf = $this->get(route('stektips.show', ['slug' => 'herfstolijfwilg']))
        ->assertOk()
        ->getContent();
    expect($olijf)->toContain('Bronnen');
    expect($olijf)->toContain('RHS');
    expect($olijf)->not->toContain('Ervaringen deelnemers');
});
