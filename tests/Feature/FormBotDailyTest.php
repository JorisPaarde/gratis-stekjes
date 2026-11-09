<?php

declare(strict_types=1);

use App\Models\FormBotDaily;
use App\Models\GeocodeCache;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

function formBotAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Botval', 'Beheer', '9']);
}

function formBotPassword(): string
{
    return implode('-', ['TestOnly', 'Botval', 'Passphrase', '9']);
}

/** @param  array<string, mixed>  $extra */
function formBotPayload(array $extra = []): array
{
    return array_merge([
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time() - 10,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'email' => 'botval@example.com',
        'password' => formBotPassword(),
    ], $extra);
}

beforeEach(function () {
    config(['stekjes.admin_password' => formBotAdminPassphrase()]);
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    Http::fake();
    RateLimiter::clear('register:127.0.0.1');
    Notification::fake();
});

test('honeypot en te-snel verhogen form_bot_daily zonder PII in log', function () {
    Log::spy();
    expect(Schema::hasTable('form_bot_daily'))->toBeTrue();

    $this->post(route('account.registreren'), formBotPayload([
        'email' => 'honeypot-bot@example.com',
        FormGuard::HONEYPOT_FIELD => 'https://spam.test',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), formBotPayload([
            'email' => 'snel-bot@example.com',
            'form_started' => time(),
        ]))
        ->assertRedirect(route('account.registreren', ['fout' => 'snel']));

    $today = now()->timezone('Europe/Amsterdam')->toDateString();

    $honeypot = FormBotDaily::query()
        ->where('date', $today)
        ->where('form', 'register')
        ->where('reason', 'honeypot')
        ->value('count');
    $tooFast = FormBotDaily::query()
        ->where('date', $today)
        ->where('form', 'register')
        ->where('reason', 'too_fast')
        ->value('count');

    expect((int) $honeypot)->toBeGreaterThanOrEqual(1)
        ->and((int) $tooFast)->toBeGreaterThanOrEqual(1);

    Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
        return $message === 'form.bot_rejected'
            && isset($context['form'], $context['reason'])
            && ! isset($context['email'])
            && ! isset($context['ip']);
    })->atLeast()->twice();
});

test('statistieken toont botval-teller per reden zonder e-mail of IP', function () {
    $today = now()->timezone('Europe/Amsterdam')->toDateString();

    FormBotDaily::query()->insert([
        ['date' => $today, 'form' => 'register', 'reason' => 'honeypot', 'count' => 3],
        ['date' => $today, 'form' => 'register', 'reason' => 'too_fast', 'count' => 2],
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Botval · 30 dagen', false)
        ->assertSee('Honeypot', false)
        ->assertSee('Te snel', false)
        ->assertSee('Registreren', false)
        ->getContent();

    expect($html)->toContain('Vandaag: 5')
        ->and($html)->toContain('honeypot 3')
        ->and($html)->toContain('te snel 2')
        ->and($html)->toContain('Botval · 30 dagen')
        ->and($html)->not->toContain('botval@example.com')
        ->and($html)->not->toContain('@example.com');
});

test('logBotRejected blijft stil als form_bot_daily ontbreekt', function () {
    Schema::rename('form_bot_daily', 'form_bot_daily_hidden_for_test');

    try {
        FormGuard::logBotRejected('register', 'honeypot');
        expect(true)->toBeTrue();
    } finally {
        if (Schema::hasTable('form_bot_daily_hidden_for_test')) {
            Schema::rename('form_bot_daily_hidden_for_test', 'form_bot_daily');
        }
    }
});
