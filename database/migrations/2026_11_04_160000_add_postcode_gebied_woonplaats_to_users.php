<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Openbaar locatieveld: postcodegebied (4 cijfers) + woonplaats naast locatie_label.
 * Label blijft de weergave («1326 Almere»); volledige postcode/huisnummer nooit openbaar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('postcode_gebied', 4)->nullable()->after('locatie_label');
            $table->string('woonplaats', 80)->nullable()->after('postcode_gebied');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['postcode_gebied', 'woonplaats']);
        });
    }
};
