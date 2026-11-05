<?php

declare(strict_types=1);

use App\Models\OrganisatieInitiatief;
use App\Services\InitiatiefPaginas;
use Illuminate\Validation\ValidationException;

function initiatiefTestPassword(): string
{
    return implode('-', ['TestOnly', 'Initiatief', 'Passphrase', '9']);
}

function initiatiefAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Initiatief', 'Beheer', '9']);
}

/** Tweede org alleen voor tests — niet in productie-seed. */
function seedTestInitiatief(string $orgSlug = 'meerbomen-nu', string $initSlug = 'test-bomenhub'): OrganisatieInitiatief
{
    return OrganisatieInitiatief::query()->updateOrCreate(
        ['organisatie_slug' => $orgSlug],
        [
            'initiatief_slug' => $initSlug,
            'pagina_titel' => 'Test bomenhub deelt zaailingen',
            'intro_titel' => 'Wie zijn ze?',
            'intro_tekst' => "Eerste alinea over het testinitiatief.\n\nTweede alinea met meer uitleg.",
            'korte_naam' => 'Test bomenhub',
            'aanmeld_param' => 'testbomenhubber',
            'aanmeld_label' => 'Ik ben een Testbomenhubber',
            'site_url' => 'https://meerbomen.nu/',
            'site_link_tekst' => 'Meer over MeerBomen Nu',
            'agenda_url' => 'https://meerbomen.nu/',
            'secundaire_link_label' => null,
            'acties_leeg_tekst' => 'Er staan nu geen Test bomenhub-acties gepland op deze agenda.',
            'leden_titel' => 'Voor Testbomenhubbers',
            'leden_intro' => 'Gebruik dit gerust in je nieuwsbrief.',
            'kopieertekst' => 'Test kopieertekst voor gratisplanten.nl/'.$initSlug,
            'meta_description' => 'Testmeta voor initiatiefpagina.',
        ]
    );
}

test('onbekende initiatief-slug geeft 404', function () {
    $this->get('/bestaat-dit-initiatief-niet')
        ->assertNotFound();
});

test('reserved slug mag niet als initiatief_slug', function () {
    $svc = app(InitiatiefPaginas::class);
    expect(InitiatiefPaginas::reservedSlugs())->toContain('aanbod')
        ->and(InitiatiefPaginas::reservedSlugs())->toContain('initiatieven')
        ->and(InitiatiefPaginas::reservedSlugs())->toContain('account');

    expect(fn () => $svc->assertSlugAllowed('aanbod'))
        ->toThrow(ValidationException::class);

    expect(fn () => $svc->assertSlugAllowed('INITIATIEF'))
        ->toThrow(ValidationException::class);

    // Geldige slug (nog niet in gebruik) mag.
    $svc->assertSlugAllowed('nieuw-initiatief-xyz');
});

test('initiatief_slug moet uniek zijn', function () {
    $svc = app(InitiatiefPaginas::class);
    expect(fn () => $svc->assertSlugAllowed('struikroven'))
        ->toThrow(ValidationException::class);
});

test('organisatie met initiatief-slug: 301 van /organisaties/{org} naar /{slug}', function () {
    seedTestInitiatief();

    $this->get(route('organisaties.show', ['slug' => 'meerbomen-nu']))
        ->assertRedirect(route('initiatief.show', ['slug' => 'test-bomenhub']));

    $this->get(route('initiatief.show', ['slug' => 'test-bomenhub']))
        ->assertOk()
        ->assertSee('Test bomenhub deelt zaailingen', false)
        ->assertSee('Wie zijn ze?', false)
        ->assertSee('Doe je mee met Test bomenhub?', false)
        ->assertSee('Voor Testbomenhubbers', false)
        ->assertSee(route('account.registreren', ['testbomenhubber' => 1]), false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false);
});

test('organisaties-index en voor-organisaties linken naar /{slug} i.p.v. org-pagina', function () {
    seedTestInitiatief();

    $this->get(route('organisaties.index'))
        ->assertOk()
        ->assertSee(route('initiatief.show', ['slug' => 'test-bomenhub']), false)
        ->assertSee(route('initiatief.show', ['slug' => 'struikroven']), false)
        ->assertDontSee('/organisaties/struikroven', false)
        ->assertDontSee('/organisaties/meerbomen-nu"', false);

    $this->get(route('voor-organisaties'))
        ->assertOk()
        ->assertSee(route('initiatief.show', ['slug' => 'struikroven']), false)
        ->assertSee(route('initiatief.show', ['slug' => 'test-bomenhub']), false);

    // /initiatieven (agenda) verwijst naar organisaties; die lijst linkt Struikroven naar /struikroven.
    $this->get(route('partners'))
        ->assertOk()
        ->assertSee(route('organisaties.index'), false);
    $this->get(route('organisaties.index'))
        ->assertSee('href="'.route('initiatief.show', ['slug' => 'struikroven']).'"', false);
});

test('sitemap: initiatief-URL i.p.v. /organisaties/{org} voor orgs met slug', function () {
    seedTestInitiatief();

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/struikroven')
        ->and($xml)->toContain('/test-bomenhub')
        ->and($xml)->not->toContain('/organisaties/struikroven')
        ->and($xml)->not->toContain('/organisaties/meerbomen-nu');
});

test('poster en flyer QR bron gebruiken initiatief-slug', function () {
    seedTestInitiatief();

    $a4 = $this->get(route('poster', ['voor' => 'test-bomenhub']))->assertOk()->getContent();
    expect($a4)->toContain('data-qr-url="https://gratisplanten.nl/test-bomenhub?bron=poster-test-bomenhub"');

    $a5 = $this->get(route('poster', ['voor' => 'test-bomenhub', 'formaat' => 'a5']))->assertOk()->getContent();
    expect($a5)->toContain('data-qr-url="https://gratisplanten.nl/test-bomenhub?bron=flyer-test-bomenhub"')
        ->and($a5)->toContain('poster--a5');
});

test('generiek aanmeldvinkje via query-param', function () {
    seedTestInitiatief();

    $this->get(route('account.registreren', ['testbomenhubber' => 1]))
        ->assertOk()
        ->assertSee('Ik ben een Testbomenhubber', false)
        ->assertSee('name="testbomenhubber" value="1"', false)
        ->assertSee('checked', false);

    // Bestaande Struikrover-param blijft werken.
    $this->get(route('account.registreren', ['struikrover' => 1]))
        ->assertOk()
        ->assertSee('Ik ben Struikrover', false)
        ->assertSee('name="struikrover" value="1"', false);
});

test('beheer kan initiatiefvelden Struikroven tonen en opslaan', function () {
    config(['stekjes.admin_password' => initiatiefAdminPassphrase()]);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.organisaties.edit', ['organisatieSlug' => 'struikroven']))
        ->assertOk()
        ->assertSee('initiatief_slug', false)
        ->assertSee('struikroven', false)
        ->assertSee('Ik ben Struikrover', false)
        ->assertSee('Wie zijn de Struikrovers?', false)
        ->assertSee('gratisplanten.nl/struikroven', false);

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.organisaties.update', ['organisatieSlug' => 'struikroven']), [
            'initiatief_slug' => 'struikroven',
            'pagina_titel' => 'Struikroven redt planten van de sloop',
            'intro_titel' => 'Wie zijn de Struikrovers?',
            'intro_tekst' => "Als huizen worden gesloopt of opgeknapt, verdwijnt vaak veel gezond groen uit voor- en achtertuinen. Zonde, want volwassen planten en struiken geven karakter aan een buurt.\n\nDaarom organiseert Stichting Struikroven struikroofacties. Buurtbewoners halen dan samen de planten uit de grond en geven ze een tweede leven in hun eigen tuin, op hun balkon, in de buurt of in een logeertuin. Meedoen is gratis en iedereen is welkom, jong en oud.",
            'korte_naam' => 'Struikroven',
            'aanmeld_param' => 'struikrover',
            'aanmeld_label' => 'Ik ben Struikrover',
            'site_url' => 'https://struikroven.nu',
            'site_link_tekst' => 'Meer over Struikroven',
            'agenda_url' => 'https://struikroven.nu/agenda',
            'secundaire_link_label' => 'Over de stichting',
            'acties_leeg_tekst' => 'Er staan nu geen Struikroven-acties gepland op deze agenda. Zodra er iets bij komt, zie je het hier.',
            'leden_titel' => 'Voor Struikrovers',
            'leden_intro' => 'Wil je Gratis planten bij een actie of in je nieuwsbrief noemen? Gebruik dit gerust.',
            'kopieertekst' => 'Planten over na een struikroofactie? Of zoek je juist groen voor je tuin? Op Gratis planten deel je planten, stekjes en zaden gratis met mensen in de buurt. Kijk op gratisplanten.nl/struikroven',
            'meta_description' => 'Struikroven redt planten bij sloop en vernieuwing. Via Gratis planten deel je overgebleven planten, stekjes en zaden gratis met iemand in de buurt.',
        ])
        ->assertRedirect(route('beheer.organisaties.edit', ['organisatieSlug' => 'struikroven']))
        ->assertSessionHas('status');

    // Reserved slug geweigerd.
    $this->withSession(['admin_ok' => true])
        ->from(route('beheer.organisaties.edit', ['organisatieSlug' => 'velt']))
        ->post(route('beheer.organisaties.update', ['organisatieSlug' => 'velt']), [
            'initiatief_slug' => 'account',
            'pagina_titel' => 'Nee',
            'korte_naam' => 'Nee',
        ])
        ->assertRedirect(route('beheer.organisaties.edit', ['organisatieSlug' => 'velt']))
        ->assertSessionHasErrors('initiatief_slug');
});

test('org zonder initiatief-slug blijft op /organisaties/{slug}', function () {
    $this->get(route('organisaties.show', ['slug' => 'velt']))
        ->assertOk()
        ->assertSee('Velt', false);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/organisaties/velt');
});

test('Plantenasiel Haarlem heeft een rijke pagina zonder gratis ophalen bij het asiel', function () {
    $this->get(route('organisaties.show', ['slug' => 'plantenasiel-haarlem']))
        ->assertOk()
        ->assertSee('Een tweede thuis voor kamerplanten', false)
        ->assertSee('Jan Sluyterslaan 11, 2033 TM Haarlem', false)
        ->assertSee('Dinsdagavond van 19.00 tot 21.00 uur', false)
        ->assertSee('vrijwillige bijdrage', false)
        ->assertSee('https://www.facebook.com/plantenasielhaarlem/', false)
        ->assertSee('https://www.instagram.com/plantenasielhaarlem/', false)
        ->assertSee(route('aanbieden'), false)
        ->assertDontSee('planten brengen en ophalen', false)
        ->assertDontSee('gratis ophalen', false)
        ->assertDontSee('gratis planten ophalen', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false);
});
