<?php

declare(strict_types=1);

use App\Casts\SafeEncrypted;
use App\Models\Deelplek;
use App\Models\Feedback;
use App\Models\GeocodeCache;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

test('SafeEncrypted geeft null + isUnreadable bij verkeerde APP_KEY', function () {
    $cipher = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encrypt('geheim', false);
    $id = DB::table('feedback')->insertGetId([
        'user_id' => null,
        'page_path' => '/x',
        'body' => $cipher,
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = Feedback::query()->findOrFail($id);
    expect($item->body)->toBeNull()
        ->and($item->isUnreadable('body'))->toBeTrue()
        ->and(SafeEncrypted::VIEW_FALLBACK)->toBe('Inhoud niet leesbaar');
});

test('profielupdate wist onleesbare telefoon niet bij leeg formulier', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'country' => 'NL',
        'telefoon' => '0611111111',
    ]);

    $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encrypt('0699999999', false);
    DB::table('users')->where('id', $user->id)->update(['telefoon' => $foreign]);
    $rawBefore = (string) DB::table('users')->where('id', $user->id)->value('telefoon');

    GeocodeCache::query()->create([
        'query_key' => 'haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => 'Haarlem',
        'cached_at' => now(),
    ]);

    $this->actingAs($user->fresh())
        ->post(route('account.profiel'), [
            'voornaam' => $user->name,
            'country' => 'NL',
            'locatie' => 'Haarlem',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'));

    $rawAfter = (string) DB::table('users')->where('id', $user->id)->value('telefoon');
    expect($rawAfter)->toBe($rawBefore)
        ->and($user->fresh()->isUnreadable('telefoon'))->toBeTrue();
});

test('profielupdate mag nieuwe telefoon zetten over onleesbare waarde', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locatie_label' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'country' => 'NL',
    ]);
    $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encrypt('0699999999', false);
    DB::table('users')->where('id', $user->id)->update(['telefoon' => $foreign]);

    GeocodeCache::query()->create([
        'query_key' => 'haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => 'Haarlem',
        'cached_at' => now(),
    ]);

    $this->actingAs($user->fresh())
        ->post(route('account.profiel'), [
            'voornaam' => $user->name,
            'country' => 'NL',
            'locatie' => 'Haarlem',
            'telefoon' => '0612345678',
        ])
        ->assertRedirect(route('account.profiel'));

    $fresh = $user->fresh();
    expect($fresh->isUnreadable('telefoon'))->toBeFalse()
        ->and((string) $fresh->telefoon)->toBe('0612345678');
});

test('onleesbaar deelplek-contact_email is null en wordt niet als mailadres gebruikt', function () {
    $plek = Deelplek::query()->create([
        'slug' => 'test-plek-unreadable',
        'naam' => 'Testplek',
        'type' => 'plantenbieb',
        'plaats' => 'Haarlem',
        'provincie' => 'Noord-Holland',
        'country' => 'NL',
        'adres' => '',
        'bron_url' => 'https://example.test/plek',
        'geraadpleegd' => now()->toDateString(),
        'lat' => 52.38,
        'lng' => 4.64,
        'visible' => false,
        'source' => Deelplek::SOURCE_AANMELDING,
        'status' => Deelplek::STATUS_PENDING,
        'contact_email' => 'ok@example.test',
        'privacy_at' => now(),
    ]);

    $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encrypt('secret@old.test', false);
    DB::table('deelplekken')->where('id', $plek->id)->update(['contact_email' => $foreign]);

    $fresh = $plek->fresh();
    expect($fresh->isUnreadable('contact_email'))->toBeTrue()
        ->and($fresh->contact_email)->toBeNull();

    $contactEmail = $fresh->isUnreadable('contact_email')
        ? ''
        : trim((string) ($fresh->contact_email ?? ''));
    expect($contactEmail)->toBe('');

    $controller = file_get_contents(app_path('Http/Controllers/VoorOrganisatiesController.php'));
    expect($controller)->toContain("isUnreadable('contact_email')");
});
