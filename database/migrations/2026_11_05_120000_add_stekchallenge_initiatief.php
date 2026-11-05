<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'stekchallenge')) {
                $table->boolean('stekchallenge')->nullable()->default(false);
            }
        });

        if (Schema::hasTable('organisatie_initiatieven')) {
            $exists = DB::table('organisatie_initiatieven')
                ->where('initiatief_slug', 'stekchallenge')
                ->exists();
            if (! $exists) {
                DB::table('organisatie_initiatieven')->insert([
                    'organisatie_slug' => 'carpe-vitam',
                    'initiatief_slug' => 'stekchallenge',
                    'pagina_titel' => 'STEKchallenge: november is stekmaand',
                    'intro_titel' => 'Stekken en delen, overal in Nederland',
                    'intro_tekst' => "November is stekmaand. Met de STEKchallenge van Carpe Vitam kun je overal in Nederland meedoen: thuis, op school, in de buurt of op het werk. Het is gratis.\n\nDe aftrap is zondag 8 november: een stekworkshop bij Carpe Vitam in Elim. Initiatiefnemer is klimaatburgemeester Dewy Mulder.\n\nStekken registreer je via de site van Carpe Vitam. Je krijgt dan per mail een persoonlijke link om later bij te werken. In maart volgt een terugblik. Deel je stekken met #STEKchallenge.",
                    'korte_naam' => 'STEKchallenge',
                    'aanmeld_param' => 'stekchallenge',
                    'aanmeld_label' => 'Ik doe mee aan de STEKchallenge',
                    'site_url' => 'https://carpevitam.net/stekchallenge/',
                    'site_link_tekst' => 'Registreer je stekken bij Carpe Vitam',
                    'agenda_url' => 'https://carpevitam.net/event/stek-challenge-workshop-stekken-8-november/',
                    'secundaire_link_label' => 'Over Carpe Vitam',
                    'acties_leeg_tekst' => 'Er staan nu geen STEKchallenge-acties gepland op deze agenda. Zodra er iets bij komt, zie je het hier.',
                    'leden_titel' => 'Voor wie meedoet',
                    'leden_intro' => 'Wil je Gratis planten noemen bij een stekmiddag of in je nieuwsbrief? Gebruik dit gerust. Poster en flyer kun je hieronder downloaden.',
                    'kopieertekst' => 'November is stekmaand: doe mee met de STEKchallenge. Stek thuis, op school, in de buurt of op het werk en deel met #STEKchallenge. Meer op gratisplanten.nl/stekchallenge',
                    'meta_description' => 'November is stekmaand. Doe gratis mee met de STEKchallenge van Carpe Vitam: stekworkshop 8 november in Elim, of stek thuis, op school, in de buurt of op het werk.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('evenementen')) {
            $url = 'https://carpevitam.net/event/stek-challenge-workshop-stekken-8-november/';
            $exists = DB::table('evenementen')
                ->where('url', $url)
                ->whereDate('start_datum', '2026-11-08')
                ->exists();
            if (! $exists) {
                DB::table('evenementen')->insert([
                    'titel' => 'STEKchallenge-workshop stekken (11–15u)',
                    'type' => 'overig',
                    'start_datum' => '2026-11-08',
                    'eind_datum' => null,
                    'plaats' => 'Elim',
                    'provincie' => 'Drenthe',
                    'organisator' => 'Carpe Vitam',
                    'organisatie_slug' => 'carpe-vitam',
                    'url' => $url,
                    'bron' => 'carpevitam.net',
                    'visible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('evenementen')) {
            DB::table('evenementen')
                ->where('url', 'https://carpevitam.net/event/stek-challenge-workshop-stekken-8-november/')
                ->whereDate('start_datum', '2026-11-08')
                ->delete();
        }
        if (Schema::hasTable('organisatie_initiatieven')) {
            DB::table('organisatie_initiatieven')
                ->where('initiatief_slug', 'stekchallenge')
                ->delete();
        }
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'stekchallenge')) {
                $table->dropColumn('stekchallenge');
            }
        });
    }
};
