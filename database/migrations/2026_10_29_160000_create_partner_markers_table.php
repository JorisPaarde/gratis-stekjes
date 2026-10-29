<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_markers', function (Blueprint $table) {
            $table->id();
            $table->string('naam', 160);
            $table->string('type', 32)->default('partner');
            $table->string('plaats', 120)->default('');
            $table->string('beschrijving', 500)->default('');
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->string('url', 500)->default('');
            $table->string('bron', 500)->default('');
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['type', 'visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_markers');
    }
};
