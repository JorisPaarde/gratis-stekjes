<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo-data voor lokale audits en screenshots (niet voor productie-deploy).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['label' => 'Amsterdam', 'lat' => 52.3676, 'lon' => 4.9041],
            ['label' => 'Rotterdam', 'lat' => 51.9244, 'lon' => 4.4777],
            ['label' => 'Utrecht', 'lat' => 52.0907, 'lon' => 5.1214],
            ['label' => 'Haarlem', 'lat' => 52.3874, 'lon' => 4.6462],
            ['label' => 'Groningen', 'lat' => 53.2194, 'lon' => 6.5665],
            ['label' => 'Eindhoven', 'lat' => 51.4416, 'lon' => 5.4697],
            ['label' => 'Maastricht', 'lat' => 50.8514, 'lon' => 5.6910],
            ['label' => 'Leeuwarden', 'lat' => 53.2012, 'lon' => 5.7999],
        ];

        $users = collect($locations)->map(function (array $loc, int $i) {
            return User::factory()->create([
                'name' => fake()->firstName(),
                'email' => 'demo'.($i + 1).'@example.com',
                'locatie_label' => $loc['label'],
                'lat' => $loc['lat'],
                'lon' => $loc['lon'],
                'email_verified_at' => now(),
                'active' => true,
            ]);
        });

        foreach ($users as $user) {
            Aanbod::factory()->count(2)->create([
                'user_id' => $user->id,
                'type' => 'uitdelen',
            ]);
            Aanbod::factory()->create([
                'user_id' => $user->id,
                'type' => 'ophalen',
            ]);
        }

        $a = $users[0];
        $b = $users[1];
        $aanbod = Aanbod::query()->where('user_id', $a->id)->where('type', 'uitdelen')->first();

        $conv = Conversation::query()->create([
            'user_a_id' => min($a->id, $b->id),
            'user_b_id' => max($a->id, $b->id),
            'aanbod_id' => $aanbod?->id,
            'contact_a' => null,
            'contact_b' => null,
        ]);

        Message::query()->create([
            'conversation_id' => $conv->id,
            'sender_id' => $b->id,
            'body' => 'Hoi! Mag ik een stekje komen ophalen?',
            'created_at' => now()->subHour(),
        ]);
        Message::query()->create([
            'conversation_id' => $conv->id,
            'sender_id' => $a->id,
            'body' => 'Graag, zaterdagmorgen past goed.',
            'created_at' => now()->subMinutes(30),
        ]);
    }
}
