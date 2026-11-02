<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aanbod') && ! Schema::hasColumn('aanbod', 'aantal')) {
            Schema::table('aanbod', function (Blueprint $table): void {
                // Null = niet opgegeven (oud aanbod of «één»).
                $table->unsignedSmallInteger('aantal')->nullable()->after('tekst');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aanbod', 'aantal')) {
            Schema::table('aanbod', function (Blueprint $table): void {
                $table->dropColumn('aantal');
            });
        }
    }
};
