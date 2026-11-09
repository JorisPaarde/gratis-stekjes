<?php

declare(strict_types=1);

use App\Models\PostLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('post_logs')) {
            return;
        }

        if (! Schema::hasColumn('post_logs', 'short_code')) {
            Schema::table('post_logs', function (Blueprint $table) {
                $table->string('short_code', 8)->nullable()->unique()->after('id');
            });
        }

        // Idempotente backfill: alleen rijen zonder code.
        $rows = DB::table('post_logs')->whereNull('short_code')->orderBy('id')->get(['id']);
        foreach ($rows as $row) {
            $code = PostLog::generateUniqueShortCode();
            DB::table('post_logs')->where('id', $row->id)->whereNull('short_code')->update([
                'short_code' => $code,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('post_logs') || ! Schema::hasColumn('post_logs', 'short_code')) {
            return;
        }

        Schema::table('post_logs', function (Blueprint $table) {
            $table->dropUnique(['short_code']);
            $table->dropColumn('short_code');
        });
    }
};
