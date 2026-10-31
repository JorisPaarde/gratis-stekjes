<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_mee_interests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Encrypted payloads need room beyond plaintext max lengths.
            $table->text('email');
            $table->text('note')->nullable();
            $table->timestamp('privacy_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_mee_interests');
    }
};
