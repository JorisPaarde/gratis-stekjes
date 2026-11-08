<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Models\User;
use App\Support\AanbodTekst;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('aanbod_fotos');
});

test('update met remove_fotos + ongeldige naam muteert geen disk of DB', function () {
    $user = User::factory()->create([
        'email' => 'update-order@example.com',
        'name' => 'Sam',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'active' => true,
        'email_verified_at' => now(),
    ]);

    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Monstera',
        'aantal' => 2,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $paths = [];
    foreach (['a', 'b'] as $i => $slug) {
        $path = 'keep/'.$slug.'.jpg';
        $thumb = 'keep/'.$slug.'-t.jpg';
        Storage::disk('aanbod_fotos')->put($path, 'jpeg-bytes-'.$slug);
        Storage::disk('aanbod_fotos')->put($thumb, 'thumb-bytes-'.$slug);
        $foto = AanbodFoto::query()->create([
            'aanbod_id' => $aanbod->id,
            'disk' => 'aanbod_fotos',
            'path' => $path,
            'thumb_path' => $thumb,
            'mime' => 'image/jpeg',
            'width' => 100,
            'height' => 100,
            'bytes' => 12,
            'sort_order' => $i,
            'alt' => '',
        ]);
        $paths[] = ['id' => $foto->id, 'path' => $path, 'thumb' => $thumb];
    }

    $this->actingAs($user)
        ->from(route('account.aanbod', ['edit' => $aanbod->id]))
        ->post(route('account.aanbod.update', $aanbod), [
            'type' => 'uitdelen',
            'tekst' => " \u{00A0} ",
            'aantal' => 2,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'remove_fotos' => [$paths[0]['id']],
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);

    $aanbod->refresh()->load('fotos');
    expect($aanbod->tekst)->toBe('Monstera')
        ->and($aanbod->fotos)->toHaveCount(2);

    foreach ($paths as $row) {
        expect(Storage::disk('aanbod_fotos')->exists($row['path']))->toBeTrue()
            ->and(Storage::disk('aanbod_fotos')->exists($row['thumb']))->toBeTrue()
            ->and(AanbodFoto::query()->whereKey($row['id'])->exists())->toBeTrue();
    }
});
