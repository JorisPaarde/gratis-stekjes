<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deelplekken', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('naam', 160);
            $table->string('type', 32);
            $table->string('plaats', 120);
            $table->string('provincie', 64);
            $table->string('adres', 255)->default('');
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->string('bron_url', 500);
            $table->date('geraadpleegd');
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['type', 'visible']);
            $table->index(['provincie', 'visible']);
            $table->index(['plaats', 'visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deelplekken');
    }
};
