<?php

declare(strict_types=1);

use App\Models\HelpMeeInterest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** @return list<string> */
function audienceLandingPaths(): array
{
    return ['/volkstuinen', '/help-mee', '/natuur', '/verbinding'];
}

test('doelgroep-landingspagina’s geven 200 met SEO-meta en schema', function () {
    $cases = [
        'volkstuinen' => [
            'title' => 'Volkstuinverenigingen',
            'heading' => 'Delen binnen je vereniging',
            'see' => ['tuinvereniging', 'voor-organisaties/aanmelden'],
        ],
        'help-mee' => [
            'title' => 'Help mee',
            'heading' => 'Help mee',
            'see' => ['verbinders', 'Stuur interesse'],
        ],
        'natuur' => [
            'title' => 'Natuurliefhebbers',
            'heading' => 'Meer planten, meer groen',
            'see' => ['stektips', 'deelplekken'],
        ],
        'verbinding' => [
            'title' => 'Verbinding',
            'heading' => 'Planten delen, buren ontmoeten',
            'see' => ['praatje', 'help-mee'],
        ],
    ];

    foreach ($cases as $name => $case) {
        $html = $this->get(route($name))
            ->assertOk()
            ->assertSee('<h1 class="page-title">'.$case['heading'].'</h1>', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"WebPage"', false)
            ->assertSee($case['title'], false)
            ->getContent();

        foreach ($case['see'] as $needle) {
            expect($html)->toContain($needle);
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        expect(preg_match('/\b(we|wij|ons|onze)\b/iu', $text))->toBe(
            0,
            "Pagina {$name} bevat we/wij/ons/onze."
        );
        expect($html)->not->toContain('Lucy')
            ->and($html)->not->toContain('partnerschap met');
    }
});

test('doelgroeppagina’s staan in sitemap maar niet in menu of footer', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    $home = $this->get('/')->assertOk()->getContent();

    foreach (audienceLandingPaths() as $path) {
        expect($xml)->toContain($path);
    }

    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $home, $nav))->toBe(1);
    expect(preg_match('/<p class="footer-links"[^>]*>(.*?)<\/p>/s', $home, $footer))->toBe(1);

    foreach (['volkstuinen', 'help-mee', 'natuur', 'verbinding'] as $slug) {
        expect($nav[1])->not->toContain($slug);
        expect($footer[1])->not->toContain($slug);
    }

    // Homepage-CTA mag wél naar Help mee (buiten nav/footer).
    expect($home)->toMatch('/href="[^"]*help-mee"/');
    expect($home)->not->toMatch('/href="[^"]*\/volkstuinen"/')
        ->and($home)->not->toMatch('/href="[^"]*\/natuur"/')
        ->and($home)->not->toMatch('/href="[^"]*\/verbinding"/');
});

test('help-mee formulier slaat op zonder mail', function () {
    Mail::fake();
    RateLimiter::clear('help-mee:127.0.0.1');

    $this->post(route('help-mee.store'), [
        'website' => '',
        'form_started' => time() - 10,
        'name' => 'Sam',
        'email' => 'sam@example.com',
        'note' => 'Ik wil social media doen.',
        'privacy' => '1',
    ])
        ->assertRedirect(route('help-mee'))
        ->assertSessionHas('status');

    $row = HelpMeeInterest::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Sam')
        ->and((string) $row->email)->toBe('sam@example.com')
        ->and((string) $row->note)->toBe('Ik wil social media doen.')
        ->and($row->privacy_at)->not->toBeNull();

    $rawEmail = (string) DB::table('help_mee_interests')->where('id', $row->id)->value('email');
    expect($rawEmail)->not->toBe('sam@example.com')
        ->and(Crypt::decryptString($rawEmail))->toBe('sam@example.com');

    Mail::assertNothingSent();
});

test('help-mee honeypot slaat stilzwijgend niets op', function () {
    Mail::fake();

    $this->post(route('help-mee.store'), [
        'website' => 'https://spam.example',
        'form_started' => time() - 10,
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'note' => 'spam',
        'privacy' => '1',
    ])
        ->assertRedirect(route('help-mee'))
        ->assertSessionHas('status');

    expect(HelpMeeInterest::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('privacy vermeldt help-mee interesse', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Help mee-interesse', false)
        ->assertSee('geen automatische bevestigingsmail', false);
});

test('beheer toont help-mee aanmeldingen', function () {
    config(['stekjes.admin_password' => 'TestOnly-HelpMee-Beheer-9']);

    HelpMeeInterest::query()->create([
        'name' => 'Alex',
        'email' => 'alex@example.com',
        'note' => 'Volkstuinen tippen',
        'privacy_at' => now(),
    ]);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.help-mee'))
        ->assertOk()
        ->assertSee('Alex', false)
        ->assertSee('alex@example.com', false)
        ->assertSee('Volkstuinen tippen', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
