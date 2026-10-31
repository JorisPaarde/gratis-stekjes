<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Models\PartnerMarker;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('migratie voegt country toe met default NL op locatietabellen', function () {
    expect(Schema::hasColumn('users', 'country'))->toBeTrue()
        ->and(Schema::hasColumn('deelplekken', 'country'))->toBeTrue()
        ->and(Schema::hasColumn('partner_markers', 'country'))->toBeTrue();

    $user = User::factory()->create([
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
    expect($user->fresh()->country)->toBe('NL');

    $plek = Deelplek::query()->create([
        'slug' => 'test-plek-nl',
        'naam' => 'Test plek',
        'type' => 'plantenbieb',
        'plaats' => 'Haarlem',
        'provincie' => 'Noord-Holland',
        'adres' => '2011 Haarlem',
        'lat' => 52.381,
        'lng' => 4.637,
        'bron_url' => '',
        'geraadpleegd' => now()->toDateString(),
        'visible' => true,
        'source' => Deelplek::SOURCE_SEED,
        'status' => Deelplek::STATUS_APPROVED,
    ]);
    expect($plek->fresh()->country)->toBe('NL');

    $marker = PartnerMarker::query()->create([
        'naam' => 'Test marker',
        'type' => 'partner',
        'plaats' => 'Utrecht',
        'beschrijving' => '',
        'lat' => 52.09,
        'lon' => 5.12,
        'url' => '',
        'bron' => '',
        'visible' => true,
    ]);
    expect($marker->fresh()->country)->toBe('NL');
});
