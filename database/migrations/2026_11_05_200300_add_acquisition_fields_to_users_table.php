<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'acquisition_landing_path')) {
                $table->string('acquisition_landing_path', 180)->nullable()->after('stekchallenge');
            }
            if (! Schema::hasColumn('users', 'acquisition_referrer_host')) {
                $table->string('acquisition_referrer_host', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'acquisition_utm_source')) {
                $table->string('acquisition_utm_source', 80)->nullable();
            }
            if (! Schema::hasColumn('users', 'acquisition_utm_medium')) {
                $table->string('acquisition_utm_medium', 80)->nullable();
            }
            if (! Schema::hasColumn('users', 'acquisition_utm_campaign')) {
                $table->string('acquisition_utm_campaign', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'acquisition_utm_content')) {
                $table->string('acquisition_utm_content', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'acquisition_found_via')) {
                $table->string('acquisition_found_via', 40)->nullable();
            }
            if (! Schema::hasColumn('users', 'acquisition_found_via_other')) {
                $table->string('acquisition_found_via_other', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'conversion_flow_id')) {
                $table->string('conversion_flow_id', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'conversion_flow_expires_at')) {
                $table->timestamp('conversion_flow_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'acquisition_landing_path',
                'acquisition_referrer_host',
                'acquisition_utm_source',
                'acquisition_utm_medium',
                'acquisition_utm_campaign',
                'acquisition_utm_content',
                'acquisition_found_via',
                'acquisition_found_via_other',
                'conversion_flow_id',
                'conversion_flow_expires_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
