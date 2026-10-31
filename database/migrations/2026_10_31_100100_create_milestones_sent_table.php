<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('milestone', 40);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'milestone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones_sent');
    }
};
