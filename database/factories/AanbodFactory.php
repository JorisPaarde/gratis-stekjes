<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Aanbod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Aanbod>
 */
class AanbodFactory extends Factory
{
    protected $model = Aanbod::class;

    public function definition(): array
    {
        $planten = [
            'Muntstekjes',
            'Zwarte bessenstekken',
            'Monstera-stek',
            'Pothos-stekjes',
            'Rozemarijnstek',
            'Salie-scheuten',
            'Vijgstek',
            'Sanseveria-blad',
        ];

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['uitdelen', 'ophalen']),
            'tekst' => fake()->randomElement($planten).' — '.fake()->sentence(6),
            'visible' => true,
            'expires_at' => now()->addDays(45),
            'expiry_reminder_sent_at' => null,
            'renew_token' => null,
            'renew_token_expires' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
            'renew_token' => Str::random(32),
            'renew_token_expires' => now()->addDays(14),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => [
            'visible' => false,
        ]);
    }
}
