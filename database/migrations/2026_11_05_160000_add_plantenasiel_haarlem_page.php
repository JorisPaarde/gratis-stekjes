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
        Schema::table('organisatie_initiatieven', function (Blueprint $table) {
            if (! Schema::hasColumn('organisatie_initiatieven', 'praktisch_tekst')) {
                $table->text('praktisch_tekst')->nullable()->after('intro_tekst');
            }
            if (! Schema::hasColumn('organisatie_initiatieven', 'ruilen_tekst')) {
                $table->text('ruilen_tekst')->nullable()->after('praktisch_tekst');
            }
        });

        DB::table('organisatie_initiatieven')->updateOrInsert(
            ['organisatie_slug' => 'plantenasiel-haarlem'],
            [
                'initiatief_slug' => null,
                'pagina_titel' => 'Plantenasiel Haarlem',
                'intro_titel' => 'Een tweede thuis voor kamerplanten',
                'intro_tekst' => "Plantenasiel Haarlem vangt kamerplanten op die niet meer in hun huis kunnen blijven. Bijvoorbeeld omdat een plant te groot wordt, het niet goed doet of de eigenaar erop uitgekeken is. Gezonde planten krijgen een nieuw thuis via adoptie.\n\nHet asiel bestaat sinds begin 2018. Sinds 2020 draait een groepje vrijwilligers het asiel, niet uit op winst maar vanuit duurzaamheid en liefde voor planten.",
                'praktisch_tekst' => "Wijkcentrum Alleman\nJan Sluyterslaan 11, 2033 TM Haarlem\nDinsdagavond van 19.00 tot 21.00 uur\n\nBus 2 stopt bij halte Beatrixplein; vanaf daar is het ongeveer 250 meter lopen. In de omgeving kun je gratis parkeren.",
                'ruilen_tekst' => "Alle kamerplanten en stekjes zijn beschikbaar voor adoptie voor een vrijwillige bijdrage. Bij het asiel ruil je een plant voor een andere plant, of voor spullen die helpen bij de verzorging. Denk aan een zak aarde, buitenpotten, sierpotten, turfvrije potgrond of plantenvoeding. Materialen zijn daarom ook een manier om bij te dragen als geld doneren niet kan.\n\nReserveren kan niet. Hoe vaker je langskomt, hoe groter de kans dat er een plant voor je bij zit. Op hun site vind je het actuele aanbod en tips voor de verzorging.\n\nEen plant aanbieden\nKamerplanten en stekjes zijn welkom, tuinplanten niet. Ook potten, mosstokken en potgrond kunnen ze gebruiken. Breng ze langs tijdens de openingstijden. Bij twijfel kun je het asiel een bericht met een foto sturen; vertel waarom de plant weg mag en of er beestjes op zitten.",
                'korte_naam' => 'Plantenasiel Haarlem',
                'aanmeld_param' => null,
                'aanmeld_label' => '',
                'site_url' => 'https://plantenasielhaarlem.nl/',
                'site_link_tekst' => 'Meer over Plantenasiel Haarlem',
                'agenda_url' => null,
                'secundaire_link_label' => null,
                'acties_leeg_tekst' => null,
                'leden_titel' => '',
                'leden_intro' => null,
                'kopieertekst' => null,
                'meta_description' => 'Plantenasiel Haarlem vangt kamerplanten op en biedt ze aan voor adoptie. Dinsdagavond open in wijkcentrum Alleman; vrijwillige bijdrage in geld, planten of verzorgingsmaterialen.',
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('organisatie_initiatieven')
            ->where('organisatie_slug', 'plantenasiel-haarlem')
            ->delete();

        Schema::table('organisatie_initiatieven', function (Blueprint $table) {
            if (Schema::hasColumn('organisatie_initiatieven', 'ruilen_tekst')) {
                $table->dropColumn('ruilen_tekst');
            }
            if (Schema::hasColumn('organisatie_initiatieven', 'praktisch_tekst')) {
                $table->dropColumn('praktisch_tekst');
            }
        });
    }
};
