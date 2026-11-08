<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Models\Vereniging;
use App\Services\AanbodSearch;
use App\Services\VerenigingService;
use Illuminate\Support\Facades\Notification;

function ledenBlokUser(string $email, string $name = 'Sam'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'active' => true,
        'email_verified_at' => now(),
    ]);
}

function ledenBlokAanbod(User $user, string $tekst, int $aantal, \Carbon\CarbonInterface $created): Aanbod
{
    return Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'aantal' => $aantal,
        'visible' => true,
        'expires_at' => now()->addDays(30),
        'created_at' => $created,
        'updated_at' => $created,
    ]);
}

test('ledenBlok streamt voorbij >30 lege titels naar oudere named items; public pagina zonder (N stuks)-zonder-naam', function () {
    Notification::fake();

    $u = ledenBlokUser('ledenblok-stream@example.com', 'Zichtbare Zus');
    $this->actingAs($u)->post(route('account.verenigingen.store'), [
        'vereniging' => 'VTV Streamtuin',
        'plaats' => 'Haarlem',
        'type' => 'volkstuin',
    ])->assertRedirect();

    $v = Vereniging::query()->firstOrFail();
    $v->update(['status' => 'approved']);
    $this->actingAs($u)->post(route('account.verenigingen.update', $v), ['zichtbaar' => '1'])->assertRedirect();

    // 5 oudere bruikbare items (moeten na stream in het blok landen).
    $named = [];
    foreach (range(1, 5) as $i) {
        $named[] = ledenBlokAanbod($u, 'Oude plant '.$i, 2, now()->subDays(40 - $i));
    }

    // 35 nieuwere lege/nbsp-titels mét aantal>1 (zouden een vaste limit(30) vullen).
    foreach (range(1, 35) as $i) {
        ledenBlokAanbod($u, $i % 2 === 0 ? '' : "\u{00A0}", 5, now()->subMinutes(40 - $i));
    }

    $blok = app(VerenigingService::class)->ledenBlok($v, app(AanbodSearch::class));
    expect($blok['aanbod'])->toHaveCount(5);
    foreach ($blok['aanbod'] as $item) {
        expect((string) ($item['tekst'] ?? ''))->toStartWith('Oude plant')
            ->and((string) ($item['tekst'] ?? ''))->not->toBe('')
            ->and((string) ($item['tekst'] ?? ''))->not->toBe("\u{00A0}");
    }
    $ids = array_column($blok['aanbod'], 'id');
    expect($ids)->toContain($named[4]->id);

    auth()->logout();
    $html = $this->get($v->url())->assertOk()->getContent();
    expect($html)->toContain('Gedeeld door leden')
        ->and($html)->toContain('Oude plant 5')
        ->and($html)->toContain('2 stuks')
        ->and($html)->not->toMatch('/<p>\s*@?include|<p>\s*\(\s*\d+\s*stuks\)/i')
        ->and($html)->not->toMatch('/<p>\s*<span class="aanbod-aantal">5 stuks<\/span>/');
});
