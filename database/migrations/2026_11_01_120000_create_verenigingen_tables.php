<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verenigingen (tuinvereniging, volkstuin, buurttuin …) waar leden lid van kunnen zijn.
 * Door leden toegevoegd = pending tot beheer ze goedkeurt. json_slug koppelt aan een
 * bestaande organisatie uit data/organisaties.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('verenigingen')) {
            Schema::create('verenigingen', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('naam', 160);
                $table->string('plaats', 120);
                $table->string('type', 40)->default('anders');
                $table->string('json_slug', 120)->nullable()->unique();
                $table->string('status', 16)->default('pending');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('vereniging_user')) {
            Schema::create('vereniging_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vereniging_id')->constrained('verenigingen')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('zichtbaar')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['vereniging_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vereniging_user');
        Schema::dropIfExists('verenigingen');
    }
};
