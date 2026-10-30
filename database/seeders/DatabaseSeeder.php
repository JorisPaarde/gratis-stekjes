<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Verplicht: deelplekken. Demo-aanbod/gebruikers: `php artisan db:seed --class=DemoSeeder`.
     */
    public function run(): void
    {
        $this->call(DeelplekkenSeeder::class);
    }
}
