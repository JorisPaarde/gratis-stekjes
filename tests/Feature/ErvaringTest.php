<?php

declare(strict_types=1);

use App\Models\Ervaring;
use App\Models\User;
use App\Services\ErvaringService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

function ervaringAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Ervaring', 'Beheer', '9']);
}

function ervaringJpegUpload(string $name = 'plant.jpg'): UploadedFile
{
    $img = imagecreatetruecolor(32, 32);
    $green = imagecolorallocate($img, 20, 120, 40);
    imagefilledrectangle($img, 0, 0, 31, 31, $green);
    ob_start();
    imagejpeg($img, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($img);

    $path = sys_get_temp_dir().'/ervaring-'.uniqid('', true).'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

beforeEach(function () {
    config(['stekjes.admin_password' => ervaringAdminPassphrase()]);
    Storage::fake('public');
});

test('publieke ervaringenpagina is noindex zolang leeg en niet in menu', function () {
    $html = $this->get(route('ervaringen.index'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('Nog geen goedgekeurde ervaringen', false)
        ->getContent();

    $home = $this->get(route('home'))->assertOk()->getContent();
    expect($home)->not->toContain('href="'.route('ervaringen.index').'"');

    // Nav-menu mag /ervaringen niet tonen.
    expect($html)->not->toMatch('/site-nav[^>]*>[\s\S]*href="[^"]*\/ervaringen"/');
});

test('lid kan ervaring plaatsen; blijft pending tot admin goedkeurt', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ]);

    $this->actingAs($user)
        ->post(route('account.ervaringen.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => 'Ik gaf muntstekjes aan mijn buurvrouw. Leuk praatje!',
            'plaatsnaam' => 'Haarlem',
        ])
        ->assertRedirect(route('account.ervaringen'))
        ->assertSessionHas('status');

    $row = Ervaring::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe(Ervaring::STATUS_PENDING)
        ->and($row->plaatsnaam)->toBe('Haarlem');

    $this->get(route('ervaringen.index'))
        ->assertOk()
        ->assertDontSee('Ik gaf muntstekjes', false);

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.ervaringen.approve'), ['id' => $row->id])
        ->assertRedirect(route('beheer.ervaringen'));

    expect($row->fresh()->isApproved())->toBeTrue();

    $this->get(route('ervaringen.index'))
        ->assertOk()
        ->assertSee('Ik gaf muntstekjes', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);
});

test('straatadres als plaatsnaam wordt geweigerd', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ]);

    $this->actingAs($user)
        ->from(route('account.ervaringen.create'))
        ->post(route('account.ervaringen.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => 'Leuke overdracht bij de deur.',
            'plaatsnaam' => 'Bernadottelaan 273',
        ])
        ->assertRedirect(route('account.ervaringen.create'))
        ->assertSessionHasErrors('plaatsnaam');

    expect(Ervaring::query()->count())->toBe(0);
});

test('veilige foto-upload herencodeert naar jpeg zonder EXIF', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Utrecht',
        'lat' => 52.09,
        'lon' => 5.12,
    ]);

    $upload = ervaringJpegUpload();

    $this->actingAs($user)
        ->post(route('account.ervaringen.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => 'Foto van mijn stekje na de overdracht.',
            'plaatsnaam' => 'Utrecht',
            'foto' => $upload,
        ])
        ->assertRedirect(route('account.ervaringen'));

    $row = Ervaring::query()->first();
    expect($row->hasPhoto())->toBeTrue();
    Storage::disk('public')->assertExists($row->photo_path);

    $bytes = Storage::disk('public')->get($row->photo_path);
    expect(str_starts_with($bytes, "\xFF\xD8\xFF"))->toBeTrue(); // JPEG SOI
    // APP1/EXIF marker zou hier niet meer in een verse GD-encode horen als GPS-blok.
    expect($bytes)->not->toContain('Exif');
});

test('lid kan eigen ervaring verwijderen inclusief foto', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'locatie_label' => 'Leiden', 'lat' => 52.16, 'lon' => 4.49]);
    $path = 'ervaringen/'.$user->id.'/test.jpg';
    Storage::disk('public')->put($path, 'fake');

    $ervaring = Ervaring::factory()->create([
        'user_id' => $user->id,
        'photo_path' => $path,
        'plaatsnaam' => 'Leiden',
        'status' => Ervaring::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->delete(route('account.ervaringen.destroy', $ervaring))
        ->assertRedirect(route('account.ervaringen'));

    expect(Ervaring::query()->whereKey($ervaring->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

test('ervaring rate-limit max 5 per uur', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Delft',
        'lat' => 52.01,
        'lon' => 4.36,
    ]);
    RateLimiter::clear('ervaring-store:'.$user->id);

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->post(route('account.ervaringen.store'), [
                'website' => '',
                'form_started' => time() - 10,
                'body' => 'Ervaring nummer '.$i,
                'plaatsnaam' => 'Delft',
            ])
            ->assertRedirect(route('account.ervaringen'));
    }

    $this->actingAs($user)
        ->from(route('account.ervaringen.create'))
        ->post(route('account.ervaringen.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => 'Te veel',
            'plaatsnaam' => 'Delft',
        ])
        ->assertRedirect(route('account.ervaringen.create'))
        ->assertSessionHasErrors('body');
});

test('homepage ervaringenblok blijft uit zonder config-vlag', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Ervaring::factory()->approved()->create([
        'user_id' => $user->id,
        'body' => 'Zichtbaar op home alleen met vlag',
        'plaatsnaam' => 'Haarlem',
    ]);

    config(['stekjes.ervaringen_home_block_enabled' => false]);
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Zichtbaar op home alleen met vlag', false);

    config(['stekjes.ervaringen_home_block_enabled' => true]);
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Zichtbaar op home alleen met vlag', false)
        ->assertSee('id="ervaringen-home-titel"', false);
});

test('gemeente-check accepteert plaats en postcode-4', function () {
    $svc = app(ErvaringService::class);
    expect($svc->isGemeenteLevel('Haarlem'))->toBeTrue();
    expect($svc->isGemeenteLevel('2011 Haarlem'))->toBeTrue();
    expect($svc->isGemeenteLevel('Bernadottelaan 273'))->toBeFalse();
    expect($svc->isGemeenteLevel('Haarlem 12'))->toBeFalse();
});

test('niet-beeld upload wordt geweigerd', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Utrecht',
        'lat' => 52.09,
        'lon' => 5.12,
    ]);
    $tmp = sys_get_temp_dir().'/fake-'.uniqid('', true).'.jpg';
    file_put_contents($tmp, '<?php echo "nope";');
    $file = new UploadedFile($tmp, 'evil.jpg', 'image/jpeg', null, true);

    $this->actingAs($user)
        ->post(route('account.ervaringen.store'), [
            'website' => '',
            'form_started' => time() - 10,
            'body' => 'Geen echte foto.',
            'plaatsnaam' => 'Utrecht',
            'foto' => $file,
        ])
        ->assertSessionHasErrors('foto');

    expect(Ervaring::query()->count())->toBe(0);
    @unlink($tmp);
});
