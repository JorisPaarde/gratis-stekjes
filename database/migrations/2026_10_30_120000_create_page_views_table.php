<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('page_views')) {
            return;
        }

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('path', 180);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['date', 'path']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
