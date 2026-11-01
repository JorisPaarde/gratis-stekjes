<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Models\GeocodeCache;
use App\Notifications\DeelplekAanmeldingAdminMelding;
use App\Notifications\DeelplekAanmeldingBevestiging;
use App\Services\Organisaties;
use App\Support\Geo;
use Database\Seeders\DeelplekkenSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

function aanmeldPayload(array $overrides = []): array
{
    return array_merge([
        'naam' => 'Demo Voedselbos Utrecht',
        'type' => 'voedselbos',
        'country' => 'NL',
        'adres' => 'Neude 1',
        'postcode' => '3512 AD',
        'plaats' => 'Utrecht',
        'url' => 'https://example.org/voedselbos',
        'beschrijving' => 'Open dagen en stekjesruil.',
        'contact_email' => 'aanmelder@example.org',
        'privacy' => '1',
        'website' => '',
        'form_started' => time() - 10,
    ], $overrides);
}

function seedGeoUtrecht(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('Neude 1 3512 AD Utrecht')],
        [
            'lat' => 52.0929,
            'lon' => 5.1182,
            'label' => 'Neude, Utrecht',
            'cached_at' => now(),
        ]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('Utrecht')],
        [
            'lat' => 52.0907,
            'lon' => 5.1214,
            'label' => 'Utrecht',
            'cached_at' => now(),
        ]
    );
}

beforeEach(function () {
    config([
        'stekjes.form_min_seconds' => 3,
        'stekjes.admin_email' => 'admin@example.org',
        'mail.from.address' => 'admin@example.org',
    ]);
    seedGeoUtrecht();
});

test('organisaties JSON is geldig', function () {
    expect(app(Organisaties::class)->validateJson())->toBe([]);
    expect(app(Organisaties::class)->all())->not->toBeEmpty();
});

test('voor-organisaties pagina en links vanuit footer en deelplekken', function () {
    $this->get(route('voor-organisaties'))
        ->assertOk()
        ->assertSee('Voor organisaties', false)
        ->assertSee(route('voor-organisaties.aanmelden'), false)
        ->assertSee('deelplekken', false);

    $this->get(route('deelplekken'))
        ->assertOk()
        ->assertSee(route('voor-organisaties'), false)
        ->assertSee('Organisatie of plek aanmelden', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('voor-organisaties'), false);
});

test('organisatiepagina met deelplekken rendert 200', function () {
    $this->seed(DeelplekkenSeeder::class);

    $this->get(route('organisaties.show', ['slug' => 'plantenasiel-zeist']))
        ->assertOk()
        ->assertSee('Plantenasiel Zeist', false)
        ->assertSee('plantenasielzeist.nl', false)
        ->assertSee('Bron', false)
        ->assertSee('Op de deelplekkenkaart', false)
        ->assertDontSee('Niet te bezoeken via mijn', false);

    // Deelplek verborgen (niet gratis): organisatiepagina blijft, zonder kaartkoppeling.
    $this->get(route('organisaties.show', ['slug' => 'plantenasiel-haarlem']))
        ->assertOk()
        ->assertSee('Plantenasiel Haarlem', false)
        ->assertDontSee('Op de deelplekkenkaart', false)
        ->assertSee('Niet te bezoeken via mijn', false);
});

test('organisatiepagina zonder deelplek_slugs rendert 200 (geen isNotEmpty op array)', function () {
    // Lege deelplek-koppeling: controller geeft Collection; view gebruikt count() (array-veilig).
    $this->get(route('organisaties.show', ['slug' => 'velt']))
        ->assertOk()
        ->assertSee('Velt Nederland', false)
        ->assertSee('Niet te bezoeken via mijn', false)
        ->assertDontSee('Op de deelplekkenkaart', false);

    $this->get(route('organisaties.show', ['slug' => 'bestaat-niet']))
        ->assertNotFound();
});

test('struikroven pagina 200, Struikrovers, geen deelplekken, kruislink MeerBomen Nu', function () {
    $this->get(route('organisaties.show', ['slug' => 'struikroven']))
        ->assertOk()
        ->assertSee('Stichting Struikroven', false)
        ->assertSee('Struikrovers', false)
        ->assertSee('Bekijk hun actieagenda', false)
        ->assertSee('https://www.struikroven.nu/agenda', false)
        ->assertSee('Niet te bezoeken via mijn', false)
        ->assertDontSee('Op de deelplekkenkaart', false)
        ->assertSee(route('organisaties.show', ['slug' => 'meerbomen-nu']), false)
        ->assertSee('MeerBomen Nu', false);

    $this->get(route('organisaties.show', ['slug' => 'meerbomen-nu']))
        ->assertOk()
        ->assertSee(route('organisaties.show', ['slug' => 'struikroven']), false)
        ->assertSee('Stichting Struikroven', false);

    $this->get(route('organisaties.index'))
        ->assertOk()
        ->assertSee('Stichting Struikroven', false)
        ->assertSee(route('organisaties.show', ['slug' => 'struikroven']), false);
});

test('alle organisatiepaginas 200 met config/route-cache en APP_DEBUG=false', function () {
    // Bestand-sqlite i.p.v. :memory:, anders verdwijnt het schema bij config:cache / nieuwe PDO.
    $dbPath = database_path('organisaties-cache-test.sqlite');
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

        // Deploy-achtig: config + routes gecached, debug uit (zoals staging).
        $this->artisan('config:cache')->assertSuccessful();
        $this->artisan('route:cache')->assertSuccessful();
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(DeelplekkenSeeder::class);

        $slugs = collect(app(Organisaties::class)->all())->pluck('slug')->filter()->values();
        expect($slugs)->toContain('velt');
        expect($slugs->count())->toBeGreaterThanOrEqual(10);

        foreach ($slugs as $slug) {
            $this->get('/organisaties/'.$slug)
                ->assertOk()
                ->assertDontSee('Er ging iets mis', false);
        }

        $this->get('/organisaties')->assertOk();
        $this->get('/voor-organisaties')->assertOk();
    } finally {
        $this->artisan('config:clear');
        $this->artisan('route:clear');
        config(['app.debug' => $prevDebug]);
        if (is_file($dbPath)) {
            unlink($dbPath);
        }
    }
});

test('aanmeldformulier maakt pending plek die niet publiek zichtbaar is', function () {
    Notification::fake();
    $this->seed(DeelplekkenSeeder::class);

    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload())
        ->assertRedirect(route('voor-organisaties.aanmelden'))
        ->assertSessionHas('status');

    $plek = Deelplek::query()->where('naam', 'Demo Voedselbos Utrecht')->first();
    expect($plek)->not->toBeNull();
    expect($plek->status)->toBe(Deelplek::STATUS_PENDING);
    expect($plek->visible)->toBeFalse();
    expect($plek->source)->toBe(Deelplek::SOURCE_AANMELDING);
    expect($plek->contact_email)->toBe('aanmelder@example.org');
    // AVG: geen exact straatadres in opslag.
    expect($plek->adres)->not->toContain('Neude');
    expect($plek->adres)->toContain('Utrecht');

    $this->get(route('deelplekken'))
        ->assertOk()
        ->assertDontSee('Demo Voedselbos Utrecht', false);

    $this->getJson(route('api.deelplekken'))
        ->assertOk()
        ->assertJsonMissing(['naam' => 'Demo Voedselbos Utrecht']);

    Notification::assertSentOnDemand(DeelplekAanmeldingBevestiging::class, function ($n, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'aanmelder@example.org';
    });
    Notification::assertSentOnDemand(DeelplekAanmeldingAdminMelding::class, function ($n, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'admin@example.org';
    });
});

test('aanmeldvalidatie weigert ontbrekende velden en ongeldig type', function () {
    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload([
        'naam' => '',
        'type' => 'onzin',
        'plaats' => '',
        'contact_email' => 'geen-mail',
        'privacy' => null,
    ]))->assertSessionHasErrors(['naam', 'type', 'plaats', 'contact_email', 'privacy']);

    expect(Deelplek::query()->where('source', Deelplek::SOURCE_AANMELDING)->count())->toBe(0);
});

test('honeypot bij aanmelden is stil succes zonder record of mail', function () {
    Notification::fake();

    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload([
        'website' => 'http://bot.example',
    ]))->assertRedirect(route('voor-organisaties.aanmelden'));

    expect(Deelplek::query()->where('source', Deelplek::SOURCE_AANMELDING)->count())->toBe(0);
    Notification::assertNothingSent();
});

test('aanmelden is rate-limited', function () {
    Notification::fake();
    RateLimiter::clear('deelplek-aanmelden:127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload([
            'naam' => "Plek $i",
            'contact_email' => "user{$i}@example.org",
        ]))->assertRedirect();
    }

    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload([
        'naam' => 'Plek te veel',
        'contact_email' => 'teveel@example.org',
    ]))->assertSessionHasErrors('contact_email');
});

test('approve maakt zichtbaar reject houdt verborgen', function () {
    Notification::fake();
    $this->seed(DeelplekkenSeeder::class);

    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload())->assertRedirect();
    $plek = Deelplek::query()->where('naam', 'Demo Voedselbos Utrecht')->firstOrFail();

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.deelplekken.approve'), ['id' => $plek->id])
        ->assertRedirect(route('beheer.index'));

    $plek->refresh();
    expect($plek->status)->toBe(Deelplek::STATUS_APPROVED);
    expect($plek->visible)->toBeTrue();

    $this->get(route('deelplekken'))
        ->assertOk()
        ->assertSee('Demo Voedselbos Utrecht', false);

    $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->assertSee('Demo Voedselbos Utrecht', false);

    // Tweede aanmelding → afwijzen
    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload([
        'naam' => 'Afgewezen Bos',
        'contact_email' => 'anders@example.org',
    ]))->assertRedirect();
    $rejected = Deelplek::query()->where('naam', 'Afgewezen Bos')->firstOrFail();

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.deelplekken.reject'), ['id' => $rejected->id])
        ->assertRedirect(route('beheer.index'));

    $rejected->refresh();
    expect($rejected->status)->toBe(Deelplek::STATUS_REJECTED);
    expect($rejected->visible)->toBeFalse();

    $this->get(route('deelplekken'))
        ->assertOk()
        ->assertDontSee('Afgewezen Bos', false);
});

test('seeder wist geen aanmeldingen', function () {
    Notification::fake();
    $this->seed(DeelplekkenSeeder::class);
    $this->post(route('voor-organisaties.aanmelden'), aanmeldPayload())->assertRedirect();

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.deelplekken.approve'), [
            'id' => Deelplek::query()->where('naam', 'Demo Voedselbos Utrecht')->value('id'),
        ]);

    $this->seed(DeelplekkenSeeder::class);

    expect(Deelplek::query()->where('naam', 'Demo Voedselbos Utrecht')->where('status', Deelplek::STATUS_APPROVED)->exists())->toBeTrue();
    expect(Deelplek::query()->where('source', Deelplek::SOURCE_SEED)->count())->toBe(88);
});

test('sitemap bevat voor-organisaties en organisatiepaginas', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/voor-organisaties');
    expect($xml)->toContain('/organisaties/plantenasiel-haarlem');
    expect($xml)->toContain('/organisaties/voedselbosbouw');
    expect($xml)->toContain('/organisaties/struikroven');
    expect($xml)->toContain('/organisaties/meerbomen-nu');
    expect($xml)->toContain('/organisaties/het-bomenproject');
    expect($xml)->not->toContain('/organisaties/stekjesroute');
    expect($xml)->not->toContain('/organisaties/plant-voor-plant');
});

test('plant-voor-plant URL redirect naar organisaties-overzicht', function () {
    $this->get('/organisaties/plant-voor-plant')
        ->assertRedirect('/organisaties')
        ->assertStatus(301);
});

test('verborgen stekjesroute is geen publieke organisatiepagina', function () {
    $this->get('/organisaties/stekjesroute')->assertNotFound();
    $this->get('/organisaties')
        ->assertOk()
        ->assertDontSee('Stekjesroute', false)
        ->assertSee('Het Bomenproject', false);
});

test('docs uitnodigingen bestaat en waarschuwt niet versturen', function () {
    $path = base_path('docs/uitnodigingen.md');
    expect(is_readable($path))->toBeTrue();
    $text = (string) file_get_contents($path);
    expect($text)->toContain('NIET VERSTUREN');
    expect($text)->toContain('stichting@voedselbosbouw.org');
    expect($text)->toContain('/voor-organisaties/aanmelden');
    expect($text)->toContain('op mijn site');
    expect($text)->toContain('Pagina op Gratis planten');
    expect($text)->not->toContain('op onze site');
    expect($text)->not->toContain('| Onze pagina |');
});

test('aanmeldbevestiging is ik-vorm (afzender Gratis planten)', function () {
    $plek = new Deelplek(['naam' => 'Testplek']);
    $mail = (new \App\Notifications\DeelplekAanmeldingBevestiging($plek))->toMail((object) []);
    $lines = collect($mail->introLines)->implode(' ');
    expect($lines)->toContain('Ik heb je aanmelding');
    expect($lines)->toContain('zet ik de plek');
    expect($lines)->not->toMatch('/\b[Ww]e\b/');
    expect($lines)->not->toMatch('/\b[Ww]ij\b/');
    expect((string) $mail->salutation)->not->toContain('Joris');
});
