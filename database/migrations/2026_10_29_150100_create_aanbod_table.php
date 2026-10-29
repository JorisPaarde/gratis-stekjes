<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aanbod', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // uitdelen | ophalen
            $table->text('tekst');
            $table->boolean('visible')->default(true);
            $table->timestamp('expires_at');
            $table->timestamp('expiry_reminder_sent_at')->nullable();
            $table->string('renew_token', 128)->nullable()->unique();
            $table->timestamp('renew_token_expires')->nullable();
            $table->timestamps();

            $table->index(['visible', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aanbod');
    }
};
