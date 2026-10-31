<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ervaring;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ervaring>
 */
class ErvaringFactory extends Factory
{
    protected $model = Ervaring::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->realText(120),
            'photo_path' => null,
            'plantensoort_id' => null,
            'aanbod_id' => null,
            'conversation_id' => null,
            'plaatsnaam' => fake()->city(),
            'status' => Ervaring::STATUS_PENDING,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Ervaring::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => Ervaring::STATUS_REJECTED,
            'approved_at' => null,
        ]);
    }
}
