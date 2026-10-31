<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Models\User;
use App\Services\AvgService;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function fotoUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
    ], $extra));
}

function fixtureUpload(string $name, ?string $asName = null): UploadedFile
{
    $path = dirname(__DIR__).'/fixtures/'.$name;
    expect(is_file($path))->toBeTrue("fixture {$name} ontbreekt");

    return new UploadedFile(
        $path,
        $asName ?? $name,
        mime_content_type($path) ?: 'application/octet-stream',
        null,
        true
    );
}

beforeEach(function () {
    Storage::fake('aanbod_fotos');
    // Storage::fake vervangt de disk-root; zorg dat ImageProcessor wel echte fixtures leest.
});

test('aanbod met alleen foto (zonder soortnaam) wordt geaccepteerd', function () {
    $user = fotoUser();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => '',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [fixtureUpload('plain.jpg')],
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    $aanbod = Aanbod::query()->where('user_id', $user->id)->first();
    expect($aanbod)->not->toBeNull()
        ->and($aanbod->tekst)->toBe('')
        ->and($aanbod->fotos)->toHaveCount(1);

    $foto = $aanbod->fotos->first();
    expect(Storage::disk('aanbod_fotos')->exists($foto->path))->toBeTrue()
        ->and(Storage::disk('aanbod_fotos')->exists($foto->thumb_path))->toBeTrue();
});

test('aanbod zonder soort en zonder foto wordt afgewezen', function () {
    $user = fotoUser();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => '',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('niet-afbeelding en polyglot worden geweigerd', function () {
    $user = fotoUser();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Munt',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [fixtureUpload('fake.jpg')],
        ])
        ->assertSessionHasErrors('fotos');

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);

    // Polyglot: geldige JPEG + PHP-trailer — decode mag slagen, maar na herschrijven
    // mag geen PHP-payload meer in het opgeslagen bestand zitten.
    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Munt',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [fixtureUpload('polyglot.jpg.php', 'polyglot.jpg')],
        ])
        ->assertRedirect(route('account.aanbod'));

    $foto = AanbodFoto::query()->first();
    expect($foto)->not->toBeNull();
    $blob = Storage::disk('aanbod_fotos')->get($foto->path);
    expect($blob)->not->toContain('<?php')
        ->and($blob)->not->toContain('pwn');
});

test('GPS EXIF wordt gestript bij herschrijven', function () {
    $src = dirname(__DIR__).'/fixtures/with-gps.jpg';
    $before = @exif_read_data($src);
    expect($before)->toBeArray()
        ->and($before['SectionsFound'] ?? '')->toContain('GPS');

    $processor = new ImageProcessor(ImageProcessor::DRIVER_GD);
    $out = $processor->processPath($src, 1600, 400);

    $tmp = tempnam(sys_get_temp_dir(), 'aanbod');
    file_put_contents($tmp, $out['full']);
    $after = @exif_read_data($tmp);
    @unlink($tmp);

    // imagejpeg schrijft geen EXIF: GPS-sectie moet weg zijn.
    $sections = (string) ($after['SectionsFound'] ?? '');
    expect($sections)->not->toContain('GPS');
    if (is_array($after)) {
        expect($after)->not->toHaveKey('GPSLatitude');
    }
});

test('bestand groter dan limiet wordt geweigerd', function () {
    config(['stekjes.aanbod_fotos.max_bytes' => 2048]);
    $user = fotoUser();

    // Maak een te groot JPEG in memory.
    $big = imagecreatetruecolor(800, 600);
    imagejpeg($big, $path = sys_get_temp_dir().'/big-aanbod.jpg', 95);
    imagedestroy($big);
    expect(filesize($path))->toBeGreaterThan(2048);

    $upload = new UploadedFile($path, 'big.jpg', 'image/jpeg', null, true);

    $response = $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Munt',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [$upload],
        ]);

    expect($response->getSession()->get('errors'))->not->toBeNull();
    $bag = $response->getSession()->get('errors');
    expect($bag->has('fotos') || $bag->has('fotos.0'))->toBeTrue();
    @unlink($path);
    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('foto’s verschijnen in zoek-API en media-route', function () {
    \App\Models\GeocodeCache::query()->create([
        'query_key' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => '2011',
        'cached_at' => now(),
    ]);

    $user = fotoUser();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Muntstekjes',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [fixtureUpload('plain.jpg')],
        ])
        ->assertRedirect();

    $foto = AanbodFoto::query()->first();
    expect($foto)->not->toBeNull();

    // Storage::fake schrijft in de fake root; media-controller leest absolute path.
    $this->get(route('media.aanbod-foto', ['foto' => $foto->id, 'variant' => 'thumb']))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    $json = $this->getJson(route('api.zoeken', ['q' => '2011']))->assertOk()->json();
    $item = collect($json['items'] ?? [])->firstWhere('id', $foto->aanbod_id);
    expect($item)->not->toBeNull()
        ->and($item['fotos'])->toHaveCount(1)
        ->and($item['fotos'][0])->toHaveKeys(['id', 'url', 'thumb_url', 'alt', 'width', 'height'])
        ->and($item['fotos'][0]['alt'])->toContain('Muntstekjes');
});

test('verwijderen aanbod en account wist foto’s van disk', function () {
    $user = fotoUser(['password' => bcrypt('geheim-wachtwoord-1')]);

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Basilicum',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [fixtureUpload('plain.png')],
        ])
        ->assertRedirect();

    $foto = AanbodFoto::query()->first();
    $path = $foto->path;
    expect(Storage::disk('aanbod_fotos')->exists($path))->toBeTrue();

    $aanbod = $foto->aanbod;
    $this->actingAs($user)
        ->delete(route('account.aanbod.destroy', $aanbod))
        ->assertRedirect();

    expect(AanbodFoto::query()->count())->toBe(0)
        ->and(Storage::disk('aanbod_fotos')->exists($path))->toBeFalse();

    // Opnieuw plaatsen en account wissen.
    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Basilicum',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'fotos' => [fixtureUpload('plain.webp')],
        ])
        ->assertRedirect();

    $foto2 = AanbodFoto::query()->first();
    $path2 = $foto2->path;
    $dir = 'u'.$user->id;

    $result = app(AvgService::class)->deleteUserAccount($user->fresh(), 'geheim-wachtwoord-1');
    expect($result['ok'])->toBeTrue()
        ->and(AanbodFoto::query()->count())->toBe(0)
        ->and(Storage::disk('aanbod_fotos')->exists($path2))->toBeFalse()
        ->and(Storage::disk('aanbod_fotos')->exists($dir))->toBeFalse();
});

test('ImageProcessor rapporteert GD wanneer Imagick ontbreekt', function () {
    $processor = new ImageProcessor();
    expect(in_array($processor->driver(), [ImageProcessor::DRIVER_GD, ImageProcessor::DRIVER_IMAGICK], true))->toBeTrue();
});
