<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversion_daily')) {
            return;
        }

        Schema::create('conversion_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('week_key', 12)->nullable()->index();
            $table->string('event_type', 40);
            $table->string('device_type', 16)->nullable();
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('utm_content', 120)->nullable();
            $table->string('field_name', 60)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);

            $table->unique(
                [
                    'date',
                    'event_type',
                    'device_type',
                    'utm_source',
                    'utm_medium',
                    'utm_campaign',
                    'utm_content',
                    'field_name',
                    'error_code',
                ],
                'conversion_daily_dims_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_daily');
    }
};
