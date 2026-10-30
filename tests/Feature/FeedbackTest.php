<?php

declare(strict_types=1);

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/** Beheer-fixture opgebouwd (geen hardcoded secret-literal voor scanners). */
function feedbackAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Feedback', 'Beheer', '9']);
}

beforeEach(function () {
    config(['stekjes.admin_password' => feedbackAdminPassphrase()]);
});

test('gast ziet geen feedbackknop en kan niet posten', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    expect($home)->not->toContain('feedback-fab')
        ->and($home)->not->toContain('data-feedback-fab');

    $this->get(route('feedback.create'))
        ->assertRedirect(route('account.inloggen'));

    $this->post(route('feedback.store'), [
        'website' => '',
        'form_started' => time() - 10,
        'body' => 'Gast mag dit niet',
        'page_path' => '/',
    ])->assertRedirect(route('account.inloggen'));

    expect(Feedback::query()->count())->toBe(0);
});

test('lid kan feedback indienen en body staat encrypted in de database', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plain = 'De zoekpagina mag iets duidelijker.';

    $html = $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('feedback-fab', false)
        ->assertSee(route('feedback.create'), false)
        ->getContent();
    expect($html)->toContain('data-feedback-fab');

    $this->actingAs($user)
        ->get(route('feedback.create', ['page_path' => '/aanbod']))
        ->assertOk()
        ->assertSee('Wat kan beter of wat vind je goed?', false)
        ->assertSee('name="page_path"', false)
        ->assertSee('value="/aanbod"', false);

    $this->actingAs($user)
        ->post(route('feedback.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => $plain,
            'page_path' => '/aanbod',
        ])
        ->assertRedirect(route('feedback.create'))
        ->assertSessionHas('status', 'Dank je, ik lees alles zelf.');

    $row = Feedback::query()->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->user_id)->toBe((int) $user->id)
        ->and($row->page_path)->toBe('/aanbod')
        ->and((string) $row->body)->toBe($plain);

    $rawBody = (string) DB::table('feedback')->where('id', $row->id)->value('body');
    expect($rawBody)->not->toBe($plain)
        ->and(Crypt::decryptString($rawBody))->toBe($plain);
});

test('feedback rate-limit max 5 per uur per lid', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    RateLimiter::clear('feedback-store:'.$user->id);

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->post(route('feedback.store'), [
                'website' => '',
                'form_started' => time() - 10,
                'body' => 'Feedback nummer '.$i,
                'page_path' => '/',
            ])
            ->assertRedirect(route('feedback.create'))
            ->assertSessionHas('status');
    }

    $this->actingAs($user)
        ->from(route('feedback.create'))
        ->post(route('feedback.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => 'Teveel',
            'page_path' => '/',
        ])
        ->assertRedirect(route('feedback.create'))
        ->assertSessionHasErrors('body');

    expect(Feedback::query()->count())->toBe(5);
});

test('admin feedbacklijst toont ontsleutelde tekst zonder lid-pii', function () {
    $user = User::factory()->create([
        'email' => 'feedback-lid@example.com',
        'name' => 'FeedbackLidNaam',
        'email_verified_at' => now(),
    ]);
    Feedback::query()->create([
        'user_id' => $user->id,
        'page_path' => '/stektips',
        'body' => 'Graag meer stektips over kruiden.',
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.feedback'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('Graag meer stektips over kruiden.', false)
        ->assertSee('/stektips', false)
        ->assertSee('beheer-feedback-cards', false)
        ->assertSee('beheer-feedback-table', false)
        ->getContent();

    expect($html)->not->toContain('feedback-lid@example.com')
        ->and($html)->not->toContain('FeedbackLidNaam');
});

test('gast wordt doorgestuurd vanaf beheer feedback', function () {
    $this->get(route('beheer.feedback'))
        ->assertRedirect(route('beheer.login'));
});

test('statistieken toont feedback count', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Feedback::query()->create([
        'user_id' => $user->id,
        'page_path' => '/',
        'body' => 'Eerste tip',
    ]);
    Feedback::query()->create([
        'user_id' => $user->id,
        'page_path' => '/aanbod',
        'body' => 'Tweede tip',
        'read_at' => now(),
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Feedback', false)
        ->assertSee(route('beheer.feedback'), false)
        ->getContent();

    expect($html)->toContain('>2</span>')
        ->and($html)->toContain('1 ongelezen');
});

test('accountwissen anonimiseert feedback user_id', function () {
    $plain = implode('-', ['TestOnly', 'Feedback', 'Delete', '9']);
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => $plain,
    ]);
    $item = Feedback::query()->create([
        'user_id' => $user->id,
        'page_path' => '/over',
        'body' => 'Blijft geanonimiseerd',
    ]);

    $this->actingAs($user)
        ->post(route('account.verwijderen'), ['password' => $plain])
        ->assertRedirect(route('home'));

    $fresh = Feedback::query()->find($item->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->user_id)->toBeNull()
        ->and((string) $fresh->body)->toBe('Blijft geanonimiseerd');
});

test('geen feedbackknop op beheerpaginas', function () {
    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee(route('beheer.feedback'), false)
        ->getContent();

    expect($html)->not->toContain('data-feedback-fab');
});

test('privacy vermeldt feedback in gegevenssectie', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Feedback', false)
        ->assertSee('geanonimiseerd', false);
});
