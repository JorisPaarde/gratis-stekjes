<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SafeIntended;
use Illuminate\View\View;

class AudienceLandingController extends Controller
{
    /** @return list<array{slug: string, route: string, label: string}> */
    public static function series(): array
    {
        // Geen tuinverenigingen hier: die landing is mail-instap, niet deel van de
        // gedeelde doelgroep-serienav (volkstuinen e.d. blijven ongewijzigd).
        return [
            ['slug' => 'volkstuinen', 'route' => 'volkstuinen', 'label' => 'Volkstuinverenigingen'],
            ['slug' => 'help-mee', 'route' => 'help-mee', 'label' => 'Help mee'],
            ['slug' => 'natuur', 'route' => 'natuur', 'label' => 'Natuurliefhebbers'],
            ['slug' => 'verbinding', 'route' => 'verbinding', 'label' => 'Verbinding'],
        ];
    }

    public function volkstuinen(): View
    {
        $title = 'Volkstuinvereniging: planten en oogst gratis delen';
        $meta = 'Stekjes, planten en oogst delen binnen je volkstuinvereniging via '.config('site.name').'. Ook wie zaterdag niet op de tuin is, ziet wat er over is.';
        $canonical = route('volkstuinen');

        return view('pages.audience.volkstuinen', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'volkstuinen',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function tuinverenigingen(): View
    {
        $title = 'Tuinvereniging: plantmateriaal doorgeven met jullie leden';
        $meta = 'Bestuur van een tuinvereniging: voeg jullie vereniging toe via een persoonlijk account. Eigen pagina met aanbod van leden via '.config('site.name').'.';
        $canonical = route('tuinverenigingen');

        return view('pages.audience.tuinverenigingen', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'tuinverenigingen',
            'intended' => SafeIntended::ASSOCIATION,
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function helpMee(): View
    {
        $title = 'Help mee: poster, deelberichten en partner worden';
        $meta = 'Help '.config('site.name').' groeien: print een poster met QR-code, deel een bericht, word verbinder of partner. Gratis en zonder reclame.';
        $canonical = route('help-mee');

        return view('pages.audience.help-mee', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'help-mee',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function natuur(): View
    {
        $title = 'Inheemse planten gratis delen: bijen- en vlinderplanten';
        $meta = 'Inheemse planten, bijenplanten en vlinderplanten gratis delen via '.config('site.name').'. Meer groen, zonder één plant te kopen.';
        $canonical = route('natuur');

        return view('pages.audience.natuur', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'natuur',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    public function verbinding(): View
    {
        $title = 'Plantenbieb, stekjesmarkt en buurttuin: samen delen';
        $meta = 'Leer je buren kennen via een stekje. Plantenbieb, stekjesmarkt of buurttuin: delen via '.config('site.name').', met een praatje als enige prijs.';
        $canonical = route('verbinding');

        return view('pages.audience.verbinding', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'series' => self::series(),
            'currentSeries' => 'verbinding',
            'schema' => $this->webPageSchema($title, $meta, $canonical),
        ]);
    }

    /** @return array<string, mixed> */
    private function webPageSchema(string $title, string $description, string $url): array
    {
        $site = config('site.name');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $url,
            'description' => $description,
            'inLanguage' => 'nl-NL',
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $site,
                'url' => url('/'),
            ],
        ];
    }
}
