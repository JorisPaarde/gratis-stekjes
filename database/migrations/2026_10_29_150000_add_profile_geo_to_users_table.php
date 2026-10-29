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
            $table->string('telefoon', 40)->default('')->after('name');
            $table->string('locatie_label', 120)->default('')->after('telefoon');
            $table->decimal('lat', 9, 6)->nullable()->after('locatie_label');
            $table->decimal('lon', 9, 6)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefoon', 'locatie_label', 'lat', 'lon']);
        });
    }
};
