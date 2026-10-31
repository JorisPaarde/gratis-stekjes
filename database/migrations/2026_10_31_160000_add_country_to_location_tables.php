<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Landcode (NL/BE) op rijen met locatie/adres. Bestaande rijen → NL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('country', 2)->default('NL')->after('lon');
        });

        Schema::table('deelplekken', function (Blueprint $table) {
            $table->char('country', 2)->default('NL')->after('provincie');
        });

        Schema::table('partner_markers', function (Blueprint $table) {
            $table->char('country', 2)->default('NL')->after('plaats');
        });

        Schema::table('geocode_cache', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('label');
        });

        // Expliciet voor DB's die default niet terugvullen op bestaande rijen.
        DB::table('users')->whereNull('country')->orWhere('country', '')->update(['country' => 'NL']);
        DB::table('deelplekken')->whereNull('country')->orWhere('country', '')->update(['country' => 'NL']);
        DB::table('partner_markers')->whereNull('country')->orWhere('country', '')->update(['country' => 'NL']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
        });
        Schema::table('deelplekken', function (Blueprint $table) {
            $table->dropColumn('country');
        });
        Schema::table('partner_markers', function (Blueprint $table) {
            $table->dropColumn('country');
        });
        Schema::table('geocode_cache', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
