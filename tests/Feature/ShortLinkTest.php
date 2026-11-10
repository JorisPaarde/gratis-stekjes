<?php

declare(strict_types=1);

use App\Models\ConversionEvent;
use App\Models\PostLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['stekjes.post_log_mint_token' => 'test-mint-token']);
});

test('mint geeft short_url en short_code', function () {
    $response = $this->postJson('/api/internal/post-logs', [
        'titel' => 'Korte link test',
        'groep' => 'Plantenruil Test',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['short_url', 'short_code', 'tracking_url']);

    $code = (string) $response->json('short_code');
    expect($code)->toMatch('/^[2-9A-HJ-NP-Z]{5}$/')
        ->and($response->json('short_url'))->toEndWith('/s/'.$code)
        ->and($code)->not->toMatch('/[01ILO]/');
});

test('/s/{code} geeft 302 naar tracking-pad met UTM en X-Robots-Tag noindex', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Redirect check',
        'groep' => 'Stekjes Utrecht',
        'groep_slug' => 'stekjes-utrecht',
        'soort' => 'oproep',
        'utm_source' => 'instagram',
    ]);

    expect($post->short_code)->not->toBeEmpty();

    $response = $this->get('/s/'.$post->short_code);

    $response->assertRedirect()
        ->assertStatus(302)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('/ga/word-lid?')
        ->and($location)->toContain('utm_source=instagram')
        ->and($location)->toContain('utm_medium=social')
        ->and($location)->toContain('utm_campaign=stekjes-utrecht')
        ->and($location)->toContain('utm_content='.$post->id);

    // Geen open redirect: Location is relatief of op APP_URL-host.
    $host = parse_url($location, PHP_URL_HOST);
    if ($host !== null) {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        expect($host)->toBeIn(array_values(array_filter([$appHost, 'localhost'])));
    }
    expect(parse_url($location, PHP_URL_PATH) ?: $location)->toStartWith('/ga/word-lid');
});

test('/s/{code} is case-insensitive', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Case',
        'groep' => 'Test',
        'groep_slug' => 'test',
        'soort' => 'anders',
        'utm_source' => 'facebook',
    ]);

    $lower = strtolower((string) $post->short_code);
    $this->get('/s/'.$lower)
        ->assertStatus(302)
        ->assertRedirect($post->trackingPath());
});

test('onbekende short code geeft vriendelijke 404', function () {
    $this->get('/s/ZZZZZ')
        ->assertNotFound()
        ->assertSee('Oeps, dit gaat niet goed', false);
});

test('ongeldige short code tekens geven 404 via route-constraint', function () {
    // 0 en O zijn niet in het alfabet; route matcht niet → 404.
    $this->get('/s/00000')->assertNotFound();
    $this->get('/s/OOOOO')->assertNotFound();
});

test('short links staan niet in de sitemap', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Sitemap check',
        'groep' => 'Test',
        'groep_slug' => 'test',
        'soort' => 'uitleg',
        'utm_source' => 'facebook',
    ]);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->not->toContain('/s/')
        ->and($xml)->not->toContain('/s/'.$post->short_code);
});

test('short_code backfill migratie is idempotent', function () {
    // Simuleer legacy-rij zonder code (boot omzeilen via query builder).
    $id = (int) DB::table('post_logs')->insertGetId([
        'posted_on' => now()->toDateString(),
        'titel' => 'Legacy post',
        'groep' => 'Legacy',
        'groep_slug' => 'legacy',
        'soort' => 'anders',
        'utm_source' => 'facebook',
        'short_code' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('post_logs')->where('id', $id)->value('short_code'))->toBeNull();

    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_09_120000_add_short_code_to_post_logs_table.php');
    $migration->up();

    $code1 = DB::table('post_logs')->where('id', $id)->value('short_code');
    expect($code1)->toBeString()->not->toBeEmpty()
        ->and($code1)->toMatch('/^[2-9A-HJ-NP-Z]{5}$/');

    // Tweede run: zelfde code, geen crash (idempotent).
    $migration->up();
    $code2 = DB::table('post_logs')->where('id', $id)->value('short_code');
    expect($code2)->toBe($code1);

    expect(Schema::hasColumn('post_logs', 'short_code'))->toBeTrue();
});

test('beheer posts toont korte link', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Beheer short',
        'groep' => 'Beheer groep',
        'groep_slug' => 'beheer-groep',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
    ]);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.posts.index'))
        ->assertOk()
        ->assertSee('Korte link', false)
        ->assertSee($post->shortUrl(), false)
        ->assertSee('/s/'.$post->short_code, false);
});

test('/s/{code} → /ga/word-lid meet één cta_click (geen dubbeltelling op /s/)', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Meting',
        'groep' => 'Meetgroep',
        'groep_slug' => 'meetgroep',
        'soort' => 'resultaat',
        'utm_source' => 'facebook',
    ]);

    $redirect = $this->get('/s/'.$post->short_code);
    $redirect->assertStatus(302);

    $follow = $this->get($post->trackingPath());
    $follow->assertRedirect();

    expect(ConversionEvent::query()
        ->where('event_type', ConversionEvent::TYPE_CTA_CLICK)
        ->where('utm_content', (string) $post->id)
        ->count())->toBe(1);
});

test('/s/{code} met target_path redirect naar dat pad met UTM', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Custom target',
        'groep' => 'Tuinvereniging Test',
        'groep_slug' => 'tuinvereniging-test',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
        'target_path' => '/tuinverenigingen',
    ]);

    $response = $this->get('/s/'.$post->short_code);
    $response->assertStatus(302)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('/tuinverenigingen?')
        ->and($location)->not->toContain('/ga/word-lid')
        ->and($location)->toContain('utm_source=facebook')
        ->and($location)->toContain('utm_medium=social')
        ->and($location)->toContain('utm_campaign=tuinvereniging-test')
        ->and($location)->toContain('utm_content='.$post->id);

    $host = parse_url($location, PHP_URL_HOST);
    if ($host !== null) {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        expect($host)->toBeIn(array_values(array_filter([$appHost, 'localhost'])));
    }
});

test('bestaande short link zonder target_path blijft /ga/word-lid', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Legacy null target',
        'groep' => 'Legacy',
        'groep_slug' => 'legacy',
        'soort' => 'anders',
        'utm_source' => 'instagram',
        'target_path' => null,
    ]);

    $this->get('/s/'.$post->short_code)
        ->assertStatus(302)
        ->assertRedirect($post->trackingPath());

    expect($post->trackingPath())->toStartWith('/ga/word-lid?')
        ->and($post->trackingPath())->toContain('utm_source=instagram');
});

test('beheer posts toont doelpad', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Doel zichtbaar',
        'groep' => 'Beheer groep',
        'groep_slug' => 'beheer-groep',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
        'target_path' => '/stektips/kiwibes',
    ]);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.posts.index'))
        ->assertOk()
        ->assertSee('Doel:', false)
        ->assertSee('/stektips/kiwibes', false)
        ->assertSee('utm_content='.$post->id, false);
});
