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
 * @return array{users: list<User>, package: string, password_hash: string, dir: string, counts: array<string, int>, files_count: int}
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

    if (Schema::hasTable('page_views')) {
        DB::table('page_views')->insert([
            'date' => '2026-10-01',
            'path' => '/',
            'count' => 7,
        ]);
    }

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

function clearAppDataTables(): void
{
    foreach ([
        'messages', 'blocks', 'conversations', 'aanbod_fotos', 'aanbod', 'feedback', 'mail_log',
        'reports', 'partner_markers', 'geocode_cache', 'ervaringen', 'milestones_sent', 'evenementen',
        'page_views', 'import_id_maps', 'import_runs',
    ] as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->delete();
        }
    }
    if (Schema::hasTable('deelplekken') && Schema::hasColumn('deelplekken', 'source')) {
        DB::table('deelplekken')->where('source', '!=', 'seed')->delete();
    }
    DB::table('users')->delete();
}

/**
 * @return array<string, mixed>
 */
function stekjes0242FixturePayload(): array
{
    $raw = file_get_contents(base_path('tests/fixtures/stekjes-export-0.24.2.json'));
    expect($raw)->not->toBeFalse();

    /** @var array<string, mixed> $payload */
    $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    return $payload;
}

test('export schrijft package met manifest counts en files tar', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);

    expect($fx['counts']['users'])->toBe(3)
        ->and($fx['counts']['aanbod'])->toBe(1)
        ->and($fx['counts']['messages'])->toBe(1)
        ->and($fx['files_count'])->toBe(1);

    if (isset($fx['counts']['deelplekken'])) {
        expect($fx['counts']['deelplekken'])->toBe(1);
    }
    if (isset($fx['counts']['page_views'])) {
        expect($fx['counts']['page_views'])->toBe(1);
    }

    $zip = new ZipArchive;
    expect($zip->open($fx['package']))->toBeTrue();
    $json = $zip->getFromName('data.json');
    expect($json)->not->toBeFalse();
    $data = json_decode($json, true);
    expect($data['format'])->toBe(StekjesDataMigrator::FORMAT)
        ->and($data['manifest']['tables']['users']['count'])->toBe(3)
        ->and($data['tables'])->toHaveKey('page_views')
        ->and($data['tables'])->not->toHaveKey('sessions')
        ->and($data['tables'])->not->toHaveKey('import_runs');
    $zip->close();
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

test('samenvoeg-import remapt ids en behoudt bestaande prod-rijen', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);
    $hash = $fx['password_hash'];

    // Prod heeft al admin (zelfde e-mail) met andere id + eigen aanbod.
    clearAppDataTables();
    $prodHash = Hash::make('prod-admin-pass');
    DB::table('users')->insert([
        'id' => 50,
        'name' => 'ProdAdmin',
        'email' => 'admin@example.test',
        'password' => $prodHash,
        'telefoon' => '',
        'locatie_label' => 'ProdStad',
        'active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('aanbod')->insert([
        'id' => 500,
        'user_id' => 50,
        'type' => 'uitdelen',
        'tekst' => 'Bestaand prod-aanbod',
        'visible' => 1,
        'expires_at' => now()->addDays(10),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $migrator->importPackage($fx['package'], force: false, dryRun: false);

    expect($result['ok'])->toBeTrue()
        ->and($result['already_imported'])->toBeFalse()
        ->and($result['tables']['users']['prod_voor'])->toBe(1)
        ->and($result['tables']['users']['bron'])->toBe(3)
        ->and($result['tables']['users']['samengevoegd'])->toBe(1)
        ->and($result['tables']['users']['toegevoegd'])->toBe(2)
        ->and($result['tables']['users']['prod_na'])->toBe(3)
        ->and($result['tables']['aanbod']['toegevoegd'])->toBe(1)
        ->and($result['tables']['aanbod']['prod_na'])->toBe(2);

    // Prod-admin behouden (id 50, prod password, locatie niet overschreven).
    $admin = DB::table('users')->whereRaw('LOWER(email) = ?', ['admin@example.test'])->first();
    expect($admin)->not->toBeNull()
        ->and((int) $admin->id)->toBe(50)
        ->and($admin->password)->toBe($prodHash)
        ->and($admin->locatie_label)->toBe('ProdStad')
        ->and(DB::table('aanbod')->where('id', 500)->exists())->toBeTrue();

    // Bron-aanbod van user-a hangt aan geremapte nieuwe user-id (niet bron-id 10/2).
    $userA = DB::table('users')->whereRaw('LOWER(email) = ?', ['user-a@example.test'])->first();
    $importedAanbod = DB::table('aanbod')->where('tekst', 'like', '%muntstekjes%')->first();
    expect($userA)->not->toBeNull()
        ->and($importedAanbod)->not->toBeNull()
        ->and((int) $importedAanbod->user_id)->toBe((int) $userA->id)
        ->and((int) $importedAanbod->id)->not->toBe(10)
        ->and((int) $userA->id)->not->toBe(2);

    expect(is_file(storage_path('app/public/photos/synth-1.txt')))->toBeTrue();
});

test('dry-run en echte import geven dezelfde table-stats zonder blijvende writes bij dry-run', function () {
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);
    clearAppDataTables();

    $dry = $migrator->importPackage($fx['package'], force: false, dryRun: true);
    expect($dry['ok'])->toBeTrue()
        ->and($dry['dry_run'])->toBeTrue()
        ->and(DB::table('users')->count())->toBe(0)
        ->and(DB::table('import_runs')->count())->toBe(0);

    $real = $migrator->importPackage($fx['package'], force: false, dryRun: false);
    expect($real['ok'])->toBeTrue()
        ->and($real['tables'])->toEqual($dry['tables']);
});

test('duplicaat-email case-insensitive merget naar prod-account', function () {
    $migrator = app(StekjesDataMigrator::class);
    $payload = stekjes0242FixturePayload();
    clearAppDataTables();

    DB::table('users')->insert([
        'id' => 77,
        'name' => 'ProdKeep',
        'email' => 'User-A-0242@Example.TEST',
        'password' => '$2y$04$abcdefghijklmnopqrstuuPRODKEEPXXXXXXXXXXXXXXXXXXXX',
        'telefoon' => '',
        'locatie_label' => '',
        'lat' => null,
        'lon' => null,
        'active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $migrator->import($payload, force: false, dryRun: false, packageSha256: hash('sha256', 'dup-email-test'));
    expect($result['ok'])->toBeTrue()
        ->and($result['tables']['users']['samengevoegd'])->toBe(1)
        ->and($result['tables']['aanbod']['toegevoegd'])->toBe(2);

    $merged = DB::table('users')->where('id', 77)->first();
    expect($merged->locatie_label)->toBe('Testdorp') // aangevuld omdat prod leeg was
        ->and($merged->password)->toBe('$2y$04$abcdefghijklmnopqrstuuPRODKEEPXXXXXXXXXXXXXXXXXXXX');

    expect(DB::table('aanbod')->where('user_id', 77)->count())->toBe(2);
});

test('page_views counts worden opgeteld niet vervangen', function () {
    $migrator = app(StekjesDataMigrator::class);
    $payload = stekjes0242FixturePayload();
    $payload['tables']['page_views'] = [
        [
            'id' => 1,
            'date' => '2026-10-01',
            'path' => '/',
            'count' => 5,
        ],
        [
            'id' => 2,
            'date' => '2026-10-02',
            'path' => '/aanbod',
            'count' => 3,
        ],
    ];
    $payload['manifest']['tables']['page_views'] = ['count' => 2, 'sha256' => 'synthetic'];

    clearAppDataTables();
    DB::table('page_views')->insert([
        'date' => '2026-10-01',
        'path' => '/',
        'count' => 10,
    ]);

    $result = $migrator->import($payload, force: false, dryRun: false, packageSha256: hash('sha256', 'pageviews-add'));
    expect($result['ok'])->toBeTrue()
        ->and($result['tables']['page_views']['samengevoegd'])->toBe(1)
        ->and($result['tables']['page_views']['toegevoegd'])->toBe(1)
        ->and($result['tables']['page_views']['sum_voor'])->toBe(10)
        ->and($result['tables']['page_views']['sum_na'])->toBe(18);

    expect((int) DB::table('page_views')->where('date', '2026-10-01')->where('path', '/')->value('count'))->toBe(15)
        ->and((int) DB::table('page_views')->where('date', '2026-10-02')->where('path', '/aanbod')->value('count'))->toBe(3);
});

test('tweede poging met zelfde package geeft already_imported zonder wijziging', function () {
    $migrator = app(StekjesDataMigrator::class);
    $payload = stekjes0242FixturePayload();
    clearAppDataTables();

    $sha = hash('sha256', 'idempotent-package-v1');
    $first = $migrator->import($payload, force: false, dryRun: false, packageSha256: $sha);
    expect($first['ok'])->toBeTrue()->and($first['already_imported'])->toBeFalse();

    $usersAfter = (int) DB::table('users')->count();
    $aanbodAfter = (int) DB::table('aanbod')->count();
    $sumViews = Schema::hasTable('page_views') ? (int) DB::table('page_views')->sum('count') : 0;

    $second = $migrator->import($payload, force: false, dryRun: false, packageSha256: $sha);
    expect($second['ok'])->toBeTrue()
        ->and($second['already_imported'])->toBeTrue()
        ->and($second['error'])->toBe('already_imported')
        ->and(DB::table('users')->count())->toBe($usersAfter)
        ->and(DB::table('aanbod')->count())->toBe($aanbodAfter);

    if (Schema::hasTable('page_views')) {
        expect((int) DB::table('page_views')->sum('count'))->toBe($sumViews);
    }
});

test('0.24.2-fixture importeert op huidig schema als samenvoeging', function () {
    $migrator = app(StekjesDataMigrator::class);
    $payload = stekjes0242FixturePayload();
    clearAppDataTables();

    $dry = $migrator->import($payload, force: false, dryRun: true, packageSha256: hash('sha256', '0242-dry'));
    $real = $migrator->import($payload, force: false, dryRun: false, packageSha256: hash('sha256', '0242-real'));

    expect($dry['ok'])->toBeTrue()
        ->and($real['ok'])->toBeTrue()
        ->and($real['tables']['users']['toegevoegd'])->toBe(3)
        ->and($real['tables']['aanbod']['toegevoegd'])->toBe(2)
        ->and($dry['tables']['users'])->toEqual($real['tables']['users'])
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
    clearAppDataTables();

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
        ->assertJsonPath('already_imported', false)
        ->assertJsonPath('tables.users.bron', 3);

    expect(DB::table('users')->count())->toBe(0);
});

test('deploy import chunked upload flow', function () {
    config(['stekjes.deploy_import_enabled' => true]);
    $migrator = app(StekjesDataMigrator::class);
    $fx = stekjesMigrationFixture($migrator);
    clearAppDataTables();

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
        ->assertJsonPath('tables.users.toegevoegd', 3);

    expect(DB::table('users')->count())->toBe(3)
        ->and(DB::table('import_runs')->where('status', 'completed')->count())->toBe(1);
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

test('safePdoDiagnosis lekt geen waarden of e-mailadressen', function () {
    $migrator = app(StekjesDataMigrator::class);

    $e = new \PDOException(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'secret@example.test' for key 'users.users_email_unique'"
    );
    $e->errorInfo = ['23000', 1062, "Duplicate entry 'secret@example.test' for key 'users.users_email_unique'"];

    $diag = $migrator->safePdoDiagnosis($e);
    $encoded = json_encode($diag);
    expect($diag['sqlstate'])->toBe('23000')
        ->and($diag['driver_code'])->toBe(1062)
        ->and($diag['table'])->toBe('users')
        ->and($diag['column'])->toBe('email')
        ->and($diag['error_class'])->toBe('PDOException')
        ->and($encoded)->not->toContain('secret@')
        ->and($encoded)->not->toContain('example.test')
        ->and($encoded)->not->toContain('Duplicate entry');
});
