<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deelplekken', function (Blueprint $table) {
            $table->string('open_keuze', 16)->nullable()->after('beschrijving');
            $table->string('open_tekst', 200)->default('')->after('open_keuze');
        });
    }

    public function down(): void
    {
        Schema::table('deelplekken', function (Blueprint $table) {
            $table->dropColumn(['open_keuze', 'open_tekst']);
        });
    }
};
