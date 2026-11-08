<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Models\User;
use App\Services\AanbodSearch;
use App\Support\RecentAanbod;

function streamLid(): User
{
    return User::factory()->create([
        'email' => 'stream-'.uniqid('', true).'@example.com',
        'name' => 'Sam',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'active' => true,
        'email_verified_at' => now(),
    ]);
}

function streamAanbod(User $user, string $tekst, bool $foto, \Carbon\CarbonInterface $created): Aanbod
{
    $a = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'aantal' => 1,
        'visible' => true,
        'expires_at' => now()->addDays(30),
        'created_at' => $created,
        'updated_at' => $created,
    ]);
    if ($foto) {
        AanbodFoto::query()->create([
            'aanbod_id' => $a->id,
            'disk' => 'aanbod_fotos',
            'path' => 's/'.$a->id.'.jpg',
            'thumb_path' => 's/'.$a->id.'-t.jpg',
            'mime' => 'image/jpeg',
            'width' => 100,
            'height' => 100,
            'bytes' => 10,
            'sort_order' => 0,
            'alt' => '',
        ]);
    }

    return $a;
}

test('recentWithFoto streamt voorbij nieuwste NBSP-only tot oudere geldige items', function () {
    config(['gratisplanten.recent_aanbod_min' => 8]);
    $user = streamLid();

    // 8 oudere geldige mét foto (moeten in teaser landen).
    $geldig = [];
    foreach (range(1, 8) as $i) {
        $geldig[] = streamAanbod($user, 'Geldig '.$i, true, now()->subDays(40 - $i));
    }

    // 30 nieuwere NBSP-only mét foto (zouden een capped batch van 24 leegmaken).
    foreach (range(1, 30) as $i) {
        streamAanbod($user, "\u{00A0}", true, now()->subMinutes(30 - $i));
    }

    $search = app(AanbodSearch::class);
    expect($search->countPublicWithFoto())->toBeGreaterThanOrEqual(8)
        ->and(RecentAanbod::shouldShow($search->countPublicWithFoto()))->toBeTrue();

    $recent = $search->recentWithFoto(6);
    expect($recent)->toHaveCount(6);
    foreach ($recent as $item) {
        expect($item['tekst'])->toStartWith('Geldig');
    }
    $ids = array_column($recent, 'id');
    expect($ids)->toContain($geldig[7]->id);

    $api = $this->getJson(route('api.zoeken', ['recent' => 1]))
        ->assertOk()
        ->json('items');
    expect(collect($api)->pluck('tekst')->filter(fn ($t) => trim((string) $t) === '' || $t === "\u{00A0}"))
        ->toBeEmpty()
        ->and(collect($api)->pluck('tekst')->first())->toStartWith('Geldig');
});
