<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('post_logs') || Schema::hasColumn('post_logs', 'target_path')) {
            return;
        }

        Schema::table('post_logs', function (Blueprint $table) {
            $table->string('target_path', 200)->nullable()->after('utm_source');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('post_logs') || ! Schema::hasColumn('post_logs', 'target_path')) {
            return;
        }

        Schema::table('post_logs', function (Blueprint $table) {
            $table->dropColumn('target_path');
        });
    }
};
