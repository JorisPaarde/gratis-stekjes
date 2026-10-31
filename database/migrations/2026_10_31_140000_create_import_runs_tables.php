<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotente samenvoeg-import: fingerprints + oud_id→nieuw_id mapping.
 * Geen PII in deze tabellen (alleen ids / hashes / tellingen).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('import_runs')) {
            Schema::create('import_runs', function (Blueprint $table) {
                $table->id();
                $table->string('package_sha256', 64)->unique();
                $table->string('source_fingerprint', 128);
                $table->string('source_app_version', 32)->default('');
                $table->string('source_exported_at', 64)->nullable();
                $table->string('status', 16)->default('pending'); // pending | completed
                $table->json('stats_json')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('import_id_maps')) {
            Schema::create('import_id_maps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();
                $table->string('table_name', 64);
                $table->string('old_key', 191);
                $table->string('new_key', 191);
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['import_run_id', 'table_name', 'old_key'], 'import_id_maps_run_table_old_unique');
                $table->index(['table_name', 'old_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('import_id_maps');
        Schema::dropIfExists('import_runs');
    }
};
