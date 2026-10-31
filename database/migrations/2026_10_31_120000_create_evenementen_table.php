<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenementen', function (Blueprint $table) {
            $table->id();
            $table->string('titel', 200);
            $table->string('type', 32);
            $table->date('start_datum');
            $table->date('eind_datum')->nullable();
            $table->string('plaats', 120);
            $table->string('provincie', 64);
            $table->string('organisator', 160)->default('');
            $table->string('url', 500)->default('');
            $table->string('bron', 500)->default('');
            $table->date('gecontroleerd_op')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['visible', 'start_datum']);
            $table->index(['type', 'visible']);
            $table->index(['provincie', 'visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenementen');
    }
};
