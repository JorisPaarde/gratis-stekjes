<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aanbod_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aanbod_id')->constrained('aanbod')->cascadeOnDelete();
            $table->string('disk', 32)->default('aanbod_fotos');
            $table->string('path', 255);
            $table->string('thumb_path', 255);
            $table->string('mime', 32);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('alt', 255)->nullable();
            $table->timestamps();

            $table->index(['aanbod_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aanbod_fotos');
    }
};
