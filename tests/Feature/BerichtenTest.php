<?php

declare(strict_types=1);

use App\Mail\NewMessageMail;
use App\Models\Aanbod;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function messagingUser(string $email, string $name = 'Sam'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
}

test('bericht starten rond aanbod opent gesprek', function () {
    $owner = messagingUser('owner@example.com', 'Owner');
    $seeker = messagingUser('seeker@example.com', 'Seeker');
    $aanbod = Aanbod::query()->create([
        'user_id' => $owner->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($seeker)
        ->get(route('account.bericht-starten', [
            'aanbod_id' => $aanbod->id,
            'user_id' => $owner->id,
        ]))
        ->assertRedirect();

    $conv = Conversation::query()->first();
    expect($conv)->not->toBeNull();
    expect($conv->aanbod_id)->toBe($aanbod->id);
});

test('bericht versturen mailt ontvanger zonder inhoud of andermans e-mail', function () {
    Mail::fake();
    $a = messagingUser('a@example.com', 'Ada');
    $b = messagingUser('b@example.com', 'Ben');
    $conv = Conversation::query()->create([
        'user_a_id' => min($a->id, $b->id),
        'user_b_id' => max($a->id, $b->id),
        'aanbod_id' => null,
    ]);

    $this->actingAs($a)
        ->post(route('account.gesprek.store', $conv), [
            'action' => 'message',
            'body' => 'Hallo, mag ik langskomen?',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect();

    expect(Message::query()->count())->toBe(1);
    Mail::assertSent(NewMessageMail::class, function (NewMessageMail $mail) use ($b) {
        return $mail->hasTo($b->email);
    });

    $html = $this->actingAs($a)->get(route('account.gesprek', $conv))->assertOk()->getContent();
    expect($html)->toContain('Hallo, mag ik langskomen?');
    expect($html)->not->toContain('b@example.com');
});

test('e-mail pas zichtbaar na wederzijds ja', function () {
    $a = messagingUser('a2@example.com', 'Ada');
    $b = messagingUser('b2@example.com', 'Ben');
    $conv = Conversation::query()->create([
        'user_a_id' => min($a->id, $b->id),
        'user_b_id' => max($a->id, $b->id),
    ]);

    $this->actingAs($a)->post(route('account.gesprek.store', $conv), [
        'action' => 'contact',
        'choice' => 'ja',
    ]);
    $html = $this->actingAs($a)->get(route('account.gesprek', $conv))->getContent();
    expect($html)->not->toContain('b2@example.com');

    $this->actingAs($b)->post(route('account.gesprek.store', $conv), [
        'action' => 'contact',
        'choice' => 'ja',
    ]);
    $html = $this->actingAs($a)->get(route('account.gesprek', $conv))->getContent();
    expect($html)->toContain('b2@example.com');
});

test('geblokkeerde gebruiker kan geen bericht starten', function () {
    $a = messagingUser('a3@example.com');
    $b = messagingUser('b3@example.com');
    Block::query()->create([
        'blocker_id' => $b->id,
        'blocked_id' => $a->id,
        'created_at' => now(),
    ]);

    // existsBetween should catch either direction
    $this->actingAs($a)
        ->get(route('account.bericht-starten', ['user_id' => $b->id]))
        ->assertRedirect(route('aanbod'));
});

test('inbox toont gesprekken', function () {
    $a = messagingUser('a4@example.com', 'Ada');
    $b = messagingUser('b4@example.com', 'Ben');
    Conversation::query()->create([
        'user_a_id' => min($a->id, $b->id),
        'user_b_id' => max($a->id, $b->id),
    ]);

    $this->actingAs($a)
        ->get(route('account.inbox'))
        ->assertOk()
        ->assertSee('Gesprek met Ben', false);
});
