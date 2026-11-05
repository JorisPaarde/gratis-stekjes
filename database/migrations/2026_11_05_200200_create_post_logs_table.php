<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('post_logs')) {
            return;
        }

        Schema::create('post_logs', function (Blueprint $table) {
            $table->id();
            $table->date('posted_on')->index();
            $table->string('groep', 120);
            $table->string('groep_slug', 140)->index();
            $table->string('soort', 80);
            $table->string('link', 500)->nullable();
            $table->string('utm_source', 80)->default('facebook');
            $table->string('notitie', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_logs');
    }
};
