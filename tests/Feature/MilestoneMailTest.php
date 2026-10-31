<?php

declare(strict_types=1);

use App\Mail\EersteStekjeMail;
use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Ervaring;
use App\Models\MilestoneSent;
use App\Models\User;
use App\Services\MessagingService;
use App\Services\MilestoneService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

function milestoneUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'locatie_label' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'name' => 'Sam',
    ], $extra));
}

test('mijlpaalmail staat standaard uit: niets verstuurd, wel log zonder PII', function () {
    Mail::fake();
    config(['stekjes.milestone_mail_enabled' => false]);

    $captured = [];
    Log::listen(function ($level, $message = null, array $context = []) use (&$captured): void {
        // Laravel ≥10: listen krijgt MessageLogged; oudere signature: level, message, context.
        if (is_object($level) && isset($level->message)) {
            $captured[] = ['message' => $level->message, 'context' => $level->context ?? []];

            return;
        }
        $captured[] = ['message' => (string) $message, 'context' => $context];
    });

    $user = milestoneUser(['email' => 'sam-milestone@example.com']);

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'type' => 'uitdelen',
            'tekst' => 'Muntstekjes',
            'gratis_akkoord' => '1',
        ])
        ->assertRedirect(route('account.aanbod'));

    Mail::assertNothingSent();
    expect(MilestoneSent::query()->where('user_id', $user->id)->where('milestone', 'eerste_stekje')->exists())->toBeTrue();

    $hit = collect($captured)->first(
        fn (array $row) => $row['message'] === 'milestone.eerste_stekje.zou_versturen'
    );
    expect($hit)->not->toBeNull();
    $json = json_encode($hit['context']);
    expect($json)->not->toContain('sam-milestone@example.com')
        ->and($json)->not->toContain('"Sam"')
        ->and($hit['context']['mail_enabled'] ?? true)->toBeFalse();
});

test('mijlpaalmail verstuurt één keer met Mail::fake wanneer enabled', function () {
    Mail::fake();
    config(['stekjes.milestone_mail_enabled' => true]);

    $user = milestoneUser();

    app(MilestoneService::class)->maybeSendEersteStekje($user, 'eerste_aanbod');
    app(MilestoneService::class)->maybeSendEersteStekje($user, 'eerste_aanbod');

    Mail::assertSent(EersteStekjeMail::class, 1);
    expect(MilestoneSent::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('tweede aanbod triggert geen tweede mijlpaal', function () {
    Mail::fake();
    config(['stekjes.milestone_mail_enabled' => true]);
    $user = milestoneUser();

    $this->actingAs($user)->post(route('account.aanbod.store'), [
        'website' => '',
        'form_started' => time() - 10,
        'type' => 'uitdelen',
        'tekst' => 'Eerste',
        'gratis_akkoord' => '1',
    ])->assertRedirect();

    $this->actingAs($user)->post(route('account.aanbod.store'), [
        'website' => '',
        'form_started' => time() - 10,
        'type' => 'uitdelen',
        'tekst' => 'Tweede',
        'gratis_akkoord' => '1',
    ])->assertRedirect();

    Mail::assertSent(EersteStekjeMail::class, 1);
    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('overdracht afronden triggert mijlpaal voor beide leden', function () {
    Mail::fake();
    config(['stekjes.milestone_mail_enabled' => true]);

    $a = milestoneUser(['email' => 'a-overdracht@example.com']);
    $b = milestoneUser(['email' => 'b-overdracht@example.com', 'name' => 'Bo']);

    $conv = Conversation::query()->create([
        'user_a_id' => min($a->id, $b->id),
        'user_b_id' => max($a->id, $b->id),
        'contact_a' => 'ja',
        'contact_b' => 'ja',
    ]);

    $r = app(MessagingService::class)->completeOverdracht((int) $a->id, $conv, app(MilestoneService::class));
    expect($r['ok'])->toBeTrue()->and($r['first'])->toBeTrue();
    expect($conv->fresh()->overdracht_at)->not->toBeNull();

    Mail::assertSent(EersteStekjeMail::class, 2);

    // Tweede keer markeren: geen extra mail.
    app(MessagingService::class)->completeOverdracht((int) $b->id, $conv->fresh(), app(MilestoneService::class));
    Mail::assertSent(EersteStekjeMail::class, 2);
});

test('accountpagina toont badges zonder ranglijst', function () {
    $user = milestoneUser();
    Aanbod::factory()->count(5)->create(['user_id' => $user->id]);
    Ervaring::factory()->create(['user_id' => $user->id, 'plaatsnaam' => 'Haarlem']);

    $html = $this->actingAs($user)
        ->get(route('account.index'))
        ->assertOk()
        ->assertSee('Mijlpalen', false)
        ->assertSee('Eerste stekje', false)
        ->assertSee('5 gedeeld', false)
        ->assertSee('Eerste ervaring', false)
        ->assertSee('Kleine herinneringen aan wat je al deed', false)
        ->getContent();

    expect($html)->toContain('Mijn ervaringen');
});
