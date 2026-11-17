<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deelplek_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('naam', 160)->default('');
            $table->string('plaats', 120)->default('');
            $table->timestamp('deleted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deelplek_tombstones');
    }
};
