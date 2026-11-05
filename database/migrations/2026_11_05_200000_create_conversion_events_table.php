<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversion_events')) {
            return;
        }

        Schema::create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('event_type', 40)->index();
            $table->string('path', 180)->nullable();
            $table->string('flow_id', 64)->nullable()->index();
            $table->string('visitor_day_hash', 64)->nullable()->index();
            $table->string('referrer_host', 120)->nullable();
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 120)->nullable()->index();
            $table->string('utm_content', 120)->nullable()->index();
            $table->string('device_type', 16)->nullable();
            $table->string('field_name', 60)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('week_key', 12)->nullable()->index();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['event_type', 'field_name', 'error_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_events');
    }
};
