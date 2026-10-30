<?php

declare(strict_types=1);

use App\Models\Feedback;
use App\Models\User;
use App\Services\StekjesDataMigrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'stekjes.deploy_token' => 'test-deploy-token',
        'stekjes.deploy_import_enabled' => false,
        'stekjes.admin_email' => 'admin@example.test',
        'mail.from.address' => 'admin@example.test',
        'site.contact_email' => 'admin@example.test',
    ]);
    RateLimiter::clear('deploy-import:127.0.0.1');
});

/**
 * Synthetische fixture: geen echte persoonsgegevens.
 *
 * @return array{users: list<User>, package: string, password_hash: string}
 */
function stekjesMigrationFixture(StekjesDataMigrator $migrator): array
{
    $hash = Hash::make('synthetic-pass-1');

    $admin = User::factory()->create([
        'id' => 1,
        'name' => 'SynthAdmin',
        'email' => 'admin@example.test',
        'password' => $hash,
    ]);
    // factory hashed again via cast — force raw hash for export/import check
    DB::table('users')->where('id', 1)->update(['password' => $hash]);

    $u2 = User::factory()->create([
        'id' => 2,
        'name' => 'SynthUserA',
        'email' => 'user-a@example.test',
        'locatie_label' => 'Testdorp',
        'lat' => 52.1,
        'lon' => 5.1,
    ]);
    $u3 = User::factory()->create([
        'id' => 3,
        'name' => 'SynthUserB',
        'email' => 'user-b@example.test',
    ]);

    DB::table('aanbod')->insert([
        'id' => 10,
        'user_id' => $u2->id,
        'type' => 'uitdelen',
        'tekst' => 'Synthetische muntstekjes photos/synth-1.txt',
        'visible' => 1,
        'expires_at' => now()->addDays(30),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('conversations')->insert([
        'id' => 20,
        'aanbod_id' => 10,
        'user_a_id' => $u2->id,
        'user_b_id' => $u3->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('messages')->insert([
        'id' => 30,
        'conversation_id' => 20,
        'sender_id' => $u3->id,
        'body' => 'Synthetisch bericht',
        'created_at' => now(),
    ]);

    DB::table('blocks')->insert([
        'blocker_id' => $u2->id,
        'blocked_id' => $u3->id,
        'created_at' => now(),
    ]);

    Feedback::query()->create([
        'id' => 40,
        'user_id' => $u2->id,
        'page_path' => '/test',
        'body' => 'Synthetische feedback',
    ]);

    if (Schema::hasTable('deelplekken') && Schema::hasColumn('deelplekken', 'source')) {
        DB::table('deelplekken')->insert([
            'slug' => 'seed-plek-x',
            'naam' => 'Seed plek',
            'type' => 'ruilkast',
            'plaats' => 'X',
            'provincie' => 'X',
            'adres' => '',
            'lat' => 52.0,
            'lng' => 5.0,
            'bron_url' => 'https://example.test',
            'geraadpleegd' => '2026-01-01',
            'visible' => true,
            'source' => 'seed',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('deelplekken')->insert([
            'slug' => 'user-plek-y',
            'naam' => 'User plek',
            'type' => 'plantenbieb',
            'plaats' => 'Y',
            'provincie' => 'Y',
            'adres' => '',
            'lat' => 52.2,
            'lng' => 5.2,
            'bron_url' => 'https://example.test/y',
            'geraadpleegd' => '2026-01-02',
            'visible' => false,
            'source' => 'aanmelding',
            'status' => 'pending',
            'beschrijving' => 'Synthetisch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $photoRel = 'photos/synth-1.txt';
    $photoAbs = storage_path('app/public/'.$photoRel);
    if (! is_dir(dirname($photoAbs))) {
        mkdir(dirname($photoAbs), 0755, true);
    }
    file_put_contents($photoAbs, 'synthetic-photo-bytes');

    $dir = storage_path('app/stekjes-test-export-'.uniqid());
    $package = $migrator->writeExportPackage($dir);

    return [
        'users' => [$admin, $u2, $u3],
        'package' => $package['package_zip'],
        'password_hash' => $hash,
        'counts' => $package['counts'],
        'files_count' => $package['files_count'],
        'dir' => $dir,
    ];
}

test('export schrijft package met manifest counts en files tar', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    expect($fx['counts']['users'])->toBe(3)
        ->and($fx['counts']['aanbod'])->toBe(1)
        ->and($fx['counts']['messages'])->toBe(1)
        ->and($fx['files_count'])->toBe(1);

    if (isset($fx['counts']['deelplekken'])) {
        // Alleen user-aanmelding, niet seed.
        expect($fx['counts']['deelplekken'])->toBe(1);
    }

    $zip = new ZipArchive;
    expect($zip->open($fx['package']))->toBeTrue();
    $json = $zip->getFromName('data.json');
    expect($json)->not->toBeFalse();
    $data = json_decode($json, true);
    expect($data['format'])->toBe(StekjesDataMigrator::FORMAT)
        ->and($data['manifest']['tables']['users']['count'])->toBe(3)
        ->and($data['manifest']['files_count'])->toBe(1);
    expect($zip->locateName('files.tar'))->toBeGreaterThanOrEqual(0);
    $zip->close();

    // Geen sessions/cache in export
    expect($data['tables'])->not->toHaveKey('sessions')
        ->and($data['tables'])->not->toHaveKey('cache')
        ->and($data['tables'])->not->toHaveKey('jobs');
});

test('artisan stekjes:export-data schrijft zip en logt alleen counts', function () {
    User::factory()->create(['email' => 'solo@example.test']);
    $out = storage_path('app/stekjes-artisan-export-'.uniqid());

    $this->artisan('stekjes:export-data', ['--path' => $out])
        ->assertSuccessful()
        ->expectsOutputToContain('users=')
        ->expectsOutputToContain('Export OK');

    expect(is_file($out.'/stekjes-export.zip'))->toBeTrue();
});

test('import behoudt primary keys en password hashes', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);
    $hash = $fx['password_hash'];

    // Leeg target alsof verse prod (behoud schema).
    DB::table('messages')->delete();
    DB::table('blocks')->delete();
    DB::table('conversations')->delete();
    DB::table('aanbod')->delete();
    DB::table('feedback')->delete();
    if (Schema::hasTable('deelplekken')) {
        DB::table('deelplekken')->where('source', '!=', 'seed')->delete();
    }
    DB::table('users')->delete();

    $result = $migrator->importPackage($fx['package'], force: false, dryRun: false);

    expect($result['ok'])->toBeTrue()
        ->and($result['counts']['users'])->toBe(3)
        ->and($result['counts']['aanbod'])->toBe(1);

    $admin = DB::table('users')->where('id', 1)->first();
    expect($admin)->not->toBeNull()
        ->and($admin->password)->toBe($hash)
        ->and(DB::table('aanbod')->where('id', 10)->exists())->toBeTrue()
        ->and(DB::table('messages')->where('id', 30)->exists())->toBeTrue();

    // Foto teruggezet
    expect(is_file(storage_path('app/public/photos/synth-1.txt')))->toBeTrue();
});

test('import dry-run schrijft niets', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    DB::table('messages')->delete();
    DB::table('blocks')->delete();
    DB::table('conversations')->delete();
    DB::table('aanbod')->delete();
    DB::table('feedback')->delete();
    DB::table('users')->delete();

    $result = $migrator->importPackage($fx['package'], force: false, dryRun: true);
    expect($result['ok'])->toBeTrue()
        ->and($result['dry_run'])->toBeTrue()
        ->and(DB::table('users')->count())->toBe(0);
});

test('import weigert bij bestaande non-admin users zonder force', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    // Target heeft al een non-admin
    expect(DB::table('users')->where('email', 'user-a@example.test')->exists())->toBeTrue();

    $result = $migrator->importPackage($fx['package'], force: false, dryRun: false);
    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBe('target_has_users');
});

test('import merget admin opzelfde email+id en skipt die user', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    // Simuleer prod met alleen admin (zelfde id+email), wis rest.
    DB::table('messages')->delete();
    DB::table('blocks')->delete();
    DB::table('conversations')->delete();
    DB::table('aanbod')->delete();
    DB::table('feedback')->delete();
    DB::table('users')->where('id', '!=', 1)->delete();

    expect(DB::table('users')->count())->toBe(1);

    $result = $migrator->importPackage($fx['package'], force: false, dryRun: false);
    expect($result['ok'])->toBeTrue()
        ->and($result['skipped']['users'] ?? 0)->toBe(1)
        ->and($result['counts']['users'])->toBe(2) // A + B
        ->and(DB::table('users')->count())->toBe(3);
});

test('deploy import endpoint uit zonder flag', function () {
    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/import', ['dry_run' => true])
        ->assertForbidden()
        ->assertJsonPath('error', 'disabled');
});

test('deploy import endpoint dry-run via multipart package', function () {
    config(['stekjes.deploy_import_enabled' => true]);
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    DB::table('messages')->delete();
    DB::table('blocks')->delete();
    DB::table('conversations')->delete();
    DB::table('aanbod')->delete();
    DB::table('feedback')->delete();
    DB::table('users')->delete();

    $this->withToken('test-deploy-token', 'Bearer')
        ->post('/deploy/import', [
            'dry_run' => '1',
            'package' => new Illuminate\Http\UploadedFile(
                $fx['package'],
                'stekjes-export.zip',
                'application/zip',
                null,
                true
            ),
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('dry_run', true)
        ->assertJsonPath('counts.users', 3)
        ->assertJsonMissingPath('tables');

    expect(DB::table('users')->count())->toBe(0);
});

test('deploy import chunked upload flow', function () {
    config(['stekjes.deploy_import_enabled' => true]);
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    DB::table('messages')->delete();
    DB::table('blocks')->delete();
    DB::table('conversations')->delete();
    DB::table('aanbod')->delete();
    DB::table('feedback')->delete();
    DB::table('users')->delete();

    $bytes = file_get_contents($fx['package']);
    expect($bytes)->not->toBeFalse();
    $sha = hash('sha256', $bytes);

    $init = $this->withToken('test-deploy-token', 'Bearer')
        ->post('/deploy/import/init', [
            'total_size' => strlen($bytes),
            'total_sha256' => $sha,
            'chunk_count' => 1,
        ])
        ->assertOk()
        ->json();

    $uploadId = $init['upload_id'];
    $chunkFile = tempnam(sys_get_temp_dir(), 'chk');
    file_put_contents($chunkFile, $bytes);

    $this->withToken('test-deploy-token', 'Bearer')
        ->post('/deploy/import/chunk', [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'chunk_sha256' => hash('sha256', $bytes),
            'chunk' => new Illuminate\Http\UploadedFile($chunkFile, 'chunk_0', 'application/octet-stream', null, true),
        ])
        ->assertOk();

    $this->withToken('test-deploy-token', 'Bearer')
        ->post('/deploy/import', [
            'upload_id' => $uploadId,
            'dry_run' => '0',
            'force' => '0',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('counts.users', 3);

    expect(DB::table('users')->count())->toBe(3);
});

test('export-from-mysqldump convert synthetische insert', function () {
    $sql = <<<'SQL'
INSERT INTO `users` (`id`, `name`, `email`, `password`, `active`, `created_at`, `updated_at`) VALUES
(7,'Synth','synth7@example.test','$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWX12',1,'2026-01-01 00:00:00','2026-01-01 00:00:00');
INSERT INTO `aanbod` (`id`, `user_id`, `type`, `tekst`, `visible`, `expires_at`, `created_at`, `updated_at`) VALUES
(70,7,'uitdelen','Synth aanbod',1,'2026-12-01 00:00:00','2026-01-01 00:00:00','2026-01-01 00:00:00');
SQL;
    $sqlPath = storage_path('app/synth-dump-'.uniqid().'.sql');
    file_put_contents($sqlPath, $sql);
    $out = storage_path('app/synth-convert-'.uniqid());

    $this->artisan('stekjes:export-from-mysqldump', [
        'sql' => $sqlPath,
        '--out' => $out,
    ])->assertSuccessful()
        ->expectsOutputToContain('users=1')
        ->expectsOutputToContain('aanbod=1');

    expect(is_file($out.'/stekjes-export.zip'))->toBeTrue();
    @unlink($sqlPath);
});

test('schema compat rapporteeert dropped columns', function () {
    $migrator = app(StekjesDataMigrator::class);
    $payload = $migrator->export();
    // Simuleer oudere export met extra kolom
    if (isset($payload['tables']['users'][0])) {
        $payload['tables']['users'][0]['legacy_col'] = 'x';
    } else {
        $payload['tables']['users'][] = [
            'id' => 99,
            'name' => 'X',
            'email' => 'x@example.test',
            'password' => 'hash',
            'legacy_col' => 'x',
        ];
        $payload['manifest']['tables']['users'] = ['count' => 1, 'sha256' => 'abc'];
    }

    $report = $migrator->schemaCompatReport($payload);
    expect($report['tables']['users']['export_only'])->toContain('legacy_col');
});
