<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    $this->app['env'] = 'testing';
});

/** Drop every table (RefreshDatabase leaves a Laravel schema). */
function wipeAllTables(): void
{
    Schema::disableForeignKeyConstraints();
    foreach (Schema::getTableListing() as $table) {
        $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
        Schema::drop($name);
    }
    Schema::enableForeignKeyConstraints();
}

test('legacy-reset weigert buiten staging', function () {
    expect(app()->environment())->toBe('testing');

    $code = Artisan::call('staging:reset-legacy-schema', ['--force' => true]);

    expect($code)->toBe(1);
    expect(Artisan::output())->toContain('APP_ENV=staging');
});

test('legacy-reset weigert zonder --force', function () {
    $this->app['env'] = 'staging';

    $code = Artisan::call('staging:reset-legacy-schema');

    expect($code)->toBe(1);
    expect(Artisan::output())->toContain('--force');
});

test('legacy-reset is no-op op schoon Laravel-schema', function () {
    $this->app['env'] = 'staging';

    // RefreshDatabase already applied Laravel migrations (users + migration row).
    $code = Artisan::call('staging:reset-legacy-schema', ['--force' => true]);

    expect($code)->toBe(0);
    expect(Artisan::output())->toContain('Geen legacy-schema');
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('legacy-reset detecteert schema_migrations en dropt tabellen', function () {
    $this->app['env'] = 'staging';
    wipeAllTables();

    Schema::create('schema_migrations', function ($table) {
        $table->string('version')->primary();
    });
    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('email');
    });
    DB::table('schema_migrations')->insert(['version' => '0001']);

    $code = Artisan::call('staging:reset-legacy-schema', ['--force' => true]);

    expect($code)->toBe(0);
    expect(Schema::hasTable('schema_migrations'))->toBeFalse();
    expect(Schema::hasTable('users'))->toBeFalse();
    expect(Artisan::output())->toContain('Legacy-schema gedetecteerd');
});

test('legacy-reset detecteert users zonder Laravel migration-rij', function () {
    $this->app['env'] = 'staging';
    wipeAllTables();

    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('email');
    });
    Schema::create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });

    $code = Artisan::call('staging:reset-legacy-schema', ['--force' => true]);

    expect($code)->toBe(0);
    expect(Schema::hasTable('users'))->toBeFalse();
    expect(Schema::hasTable('migrations'))->toBeFalse();
});

test('legacy-reset laat Laravel-schema met users-migratie met rust', function () {
    $this->app['env'] = 'staging';
    wipeAllTables();

    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('email');
    });
    Schema::create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
    DB::table('migrations')->insert([
        'migration' => '0001_01_01_000000_create_users_table',
        'batch' => 1,
    ]);

    $code = Artisan::call('staging:reset-legacy-schema', ['--force' => true]);

    expect($code)->toBe(0);
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Artisan::output())->toContain('Geen legacy-schema');
});
