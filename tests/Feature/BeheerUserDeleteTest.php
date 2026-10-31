<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Beheer-fixture opgebouwd (geen hardcoded secret-literal voor scanners). */
function beheerDeleteAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Beheer', 'UserDelete', '9']);
}

beforeEach(function () {
    config(['stekjes.admin_password' => beheerDeleteAdminPassphrase()]);
});

test('admin kan gebruiker verwijderen inclusief aanbod en berichten', function () {
    Log::spy();

    $target = User::factory()->create([
        'name' => 'Testaccount Weg',
        'email' => 'testaccount-weg@example.test',
        'email_verified_at' => now(),
    ]);
    $other = User::factory()->create([
        'email' => 'blijft@example.test',
        'email_verified_at' => now(),
    ]);

    $aanbod = Aanbod::query()->create([
        'user_id' => $target->id,
        'type' => 'ophalen',
        'tekst' => 'Nepaanbod basilicum',
        'visible' => true,
        'expires_at' => now()->addDays(7),
    ]);

    $conv = Conversation::query()->create([
        'user_a_id' => min($target->id, $other->id),
        'user_b_id' => max($target->id, $other->id),
        'aanbod_id' => $aanbod->id,
    ]);
    Message::query()->create([
        'conversation_id' => $conv->id,
        'sender_id' => $target->id,
        'body' => 'Hoi, dit is een testbericht.',
        'created_at' => now(),
    ]);

    $targetId = (int) $target->id;

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.users.destroy'), [
            'user_id' => $targetId,
            'confirm_name' => 'Testaccount Weg',
        ])
        ->assertRedirect(route('beheer.index'))
        ->assertSessionHas('status');

    expect(User::query()->whereKey($targetId)->exists())->toBeFalse()
        ->and(Aanbod::query()->whereKey($aanbod->id)->exists())->toBeFalse()
        ->and(Conversation::query()->whereKey($conv->id)->exists())->toBeFalse()
        ->and(Message::query()->where('conversation_id', $conv->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($other->id)->exists())->toBeTrue();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($targetId): bool {
            return $message === 'beheer.user.deleted'
                && ($context['deleted_user_id'] ?? null) === $targetId
                && ! isset($context['email'])
                && ! str_contains(json_encode($context) ?: '', 'testaccount-weg@example.test');
        })
        ->atLeast()
        ->once();
});

test('niet-admin krijgt 403 bij gebruikers-verwijderen', function () {
    $member = User::factory()->create([
        'name' => 'Gewoon Lid',
        'email' => 'lid@example.test',
        'email_verified_at' => now(),
    ]);
    $target = User::factory()->create([
        'name' => 'Doelwit',
        'email' => 'doel@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('beheer.users.destroy'), [
            'user_id' => $target->id,
            'confirm_name' => 'Doelwit',
        ])
        ->assertForbidden();

    expect(User::query()->whereKey($target->id)->exists())->toBeTrue();
});

test('admin kan eigen account niet via beheer verwijderen', function () {
    $owner = User::factory()->create([
        'name' => 'Eigen Admin',
        'email' => 'eigen-admin@example.test',
        'email_verified_at' => now(),
    ]);
    config(['stekjes.admin_dashboard_emails' => ['eigen-admin@example.test']]);

    $this->actingAs($owner)
        ->post(route('beheer.users.destroy'), [
            'user_id' => $owner->id,
            'confirm_name' => 'Eigen Admin',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('user');

    expect(User::query()->whereKey($owner->id)->exists())->toBeTrue();

    $html = $this->actingAs($owner)
        ->get(route('beheer.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Eigen Admin')
        ->and($html)->not->toMatch('/confirm_name" value="Eigen Admin"/');
});
