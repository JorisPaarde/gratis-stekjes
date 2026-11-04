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
        Schema::create('organisatie_initiatieven', function (Blueprint $table) {
            $table->id();
            $table->string('organisatie_slug', 80)->unique();
            $table->string('initiatief_slug', 80)->nullable()->unique();
            $table->string('pagina_titel', 200)->default('');
            $table->string('intro_titel', 200)->default('');
            $table->text('intro_tekst')->nullable();
            $table->string('korte_naam', 120)->default('');
            $table->string('aanmeld_param', 40)->nullable();
            $table->string('aanmeld_label', 120)->default('');
            $table->string('site_url', 500)->nullable();
            $table->string('site_link_tekst', 160)->nullable();
            $table->string('agenda_url', 500)->nullable();
            $table->string('secundaire_link_label', 160)->nullable();
            $table->text('acties_leeg_tekst')->nullable();
            $table->string('leden_titel', 160)->default('');
            $table->text('leden_intro')->nullable();
            $table->text('kopieertekst')->nullable();
            $table->string('meta_description', 400)->default('');
            $table->timestamps();
        });

        // Seed Struikroven from the 0.64.1 hardcoded page content.
        DB::table('organisatie_initiatieven')->insert([
            'organisatie_slug' => 'struikroven',
            'initiatief_slug' => 'struikroven',
            'pagina_titel' => 'Struikroven redt planten van de sloop',
            'intro_titel' => 'Wie zijn de Struikrovers?',
            'intro_tekst' => "Als huizen worden gesloopt of opgeknapt, verdwijnt vaak veel gezond groen uit voor- en achtertuinen. Zonde, want volwassen planten en struiken geven karakter aan een buurt.\n\nDaarom organiseert Stichting Struikroven struikroofacties. Buurtbewoners halen dan samen de planten uit de grond en geven ze een tweede leven in hun eigen tuin, op hun balkon, in de buurt of in een logeertuin. Meedoen is gratis en iedereen is welkom, jong en oud.",
            'korte_naam' => 'Struikroven',
            'aanmeld_param' => 'struikrover',
            'aanmeld_label' => 'Ik ben Struikrover',
            'site_url' => 'https://struikroven.nu',
            'site_link_tekst' => 'Meer over Struikroven',
            'agenda_url' => 'https://struikroven.nu/agenda',
            'secundaire_link_label' => 'Over de stichting',
            'acties_leeg_tekst' => 'Er staan nu geen Struikroven-acties gepland op deze agenda. Zodra er iets bij komt, zie je het hier.',
            'leden_titel' => 'Voor Struikrovers',
            'leden_intro' => 'Wil je Gratis planten bij een actie of in je nieuwsbrief noemen? Gebruik dit gerust.',
            'kopieertekst' => 'Planten over na een struikroofactie? Of zoek je juist groen voor je tuin? Op Gratis planten deel je planten, stekjes en zaden gratis met mensen in de buurt. Kijk op gratisplanten.nl/struikroven',
            'meta_description' => 'Struikroven redt planten bij sloop en vernieuwing. Via Gratis planten deel je overgebleven planten, stekjes en zaden gratis met iemand in de buurt.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('organisatie_initiatieven');
    }
};
