<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('form_bot_daily')) {
            return;
        }

        Schema::create('form_bot_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('form', 40);
            $table->string('reason', 20);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['date', 'form', 'reason']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_bot_daily');
    }
};
