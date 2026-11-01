<?php

declare(strict_types=1);

use App\Services\MilestoneService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Ken de exclusieve Pionier-badge eenmalig toe aan het lid dat als eerste iets weggaf
 * (beheerders uitgezonderd). Bestaat er al een pionier, dan verandert er niets.
 * Aanpassen kan daarna in /beheer.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $service = app(MilestoneService::class);
            if ($service->pionierUserId() !== null) {
                return;
            }
            $user = $service->eersteDeler();
            if ($user !== null) {
                $service->setPionier($user);
            }
        } catch (Throwable $e) {
            Log::warning('pionier toekennen mislukt: '.class_basename($e));
        }
    }

    public function down(): void
    {
        // Bewust leeg: de badge blijft bij het lid.
    }
};
