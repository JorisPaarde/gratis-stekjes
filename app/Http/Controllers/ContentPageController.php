<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deelplek;
use App\Services\InDeMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class ContentPageController extends Controller
{
    public function hoeWerktHet(): View
    {
        return view('pages.hoe-werkt-het', [
            'title' => 'Hoe werkt gratis planten delen',
            'metaDescription' => 'Zo deel je gratis planten, stekjes en zaden: je haalt ze zelf op en de enige tegenprestatie is een praatje. Geen verkoop.',
            'canonical' => route('hoe-werkt-het'),
        ]);
    }

    public function hulpmiddelen(): View
    {
        return view('pages.hulpmiddelen', [
            'title' => 'Wilgenwater en hulpmiddelen voor stekjes',
            'metaDescription' => 'Wilgenwater, stekpoeder, potten, stekgrond en gereedschap: wat je nodig hebt om stekjes te laten wortelen, en wat je zelf kunt maken.',
            'canonical' => route('hulpmiddelen'),
        ]);
    }

    public function over(): View
    {
        return view('pages.over', [
            'title' => 'Wie zit er achter '.config('site.name'),
            'metaDescription' => 'Ik ben '.config('site.maker').', vader van vier, volkstuinder en webbouwer. Waarom ik '.config('site.name').' gratis maak, en hoe je samen met mij meedoet.',
            'canonical' => route('over'),
        ]);
    }

    public function inDeMedia(InDeMedia $inDeMedia): View
    {
        $title = 'Gratis planten in de media';
        $meta = 'Overzicht van mediavermeldingen over Gratis planten: artikelen over gratis planten, stekjes en zaden delen in je buurt.';
        $canonical = route('in-de-media');
        $vermeldingen = $inDeMedia->all();

        $logoPad = (string) config('site.logo', '');
        $ogPad = (string) config('site.og_image', '');

        return view('pages.in-de-media', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'vermeldingen' => $vermeldingen,
            'contactEmail' => (string) config('site.contact_email'),
            // Letterlijke uitnodiging voor redacties (bewuste «wij»-vorm t.o.v. de site).
            'redactieUitnodiging' => 'Schrijf jij over Gratis planten? Dan delen wij je artikel op Facebook en schrijven we hier een kort stukje met een link terug naar jouw artikel.',
            // Bereik in woorden, zonder exact cijfer (verandert steeds).
            'redactieBereik' => 'We bereiken veel plantenliefhebbers in heel Nederland en België.',
            'deelplekkenAantal' => $this->zichtbareDeelplekkenAantal(),
            'logoUrl' => $this->publicAssetUrlIfExists($logoPad),
            'ogImageUrl' => $this->publicAssetUrlIfExists($ogPad),
            'schema' => $this->mediaSchema($title, $meta, $canonical, $vermeldingen),
        ]);
    }

    private function zichtbareDeelplekkenAantal(): int
    {
        if (! Schema::hasTable('deelplekken')) {
            return 0;
        }
        try {
            return (int) Deelplek::query()->visible()->count();
        } catch (Throwable $e) {
            Log::warning('In-de-media: deelplekken tellen mislukt', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    private function publicAssetUrlIfExists(string $pad): ?string
    {
        $pad = trim($pad);
        if ($pad === '' || ! str_starts_with($pad, '/')) {
            return null;
        }
        if (! is_file(public_path(ltrim($pad, '/')))) {
            return null;
        }

        return asset($pad);
    }

    public function doeMee(): RedirectResponse
    {
        return redirect()->route('account.registreren');
    }

    /**
     * @param  list<array{medium: string, datum: string, kop: string, samenvatting: string, url: string, datum_label: string, image: ?string, image_webp: ?string, image_alt: string, image_credit: string}>  $vermeldingen
     * @return array<string, mixed>
     */
    private function mediaSchema(string $title, string $description, string $url, array $vermeldingen): array
    {
        $site = (string) config('site.name');
        $items = [];
        foreach ($vermeldingen as $i => $row) {
            $article = [
                '@type' => 'NewsArticle',
                'headline' => $row['kop'],
                'datePublished' => $row['datum'],
                'url' => $row['url'],
                'isPartOf' => [
                    '@type' => 'Periodical',
                    'name' => $row['medium'],
                ],
                'about' => [
                    '@type' => 'Organization',
                    'name' => $site,
                    'url' => url('/'),
                ],
            ];
            if ($row['samenvatting'] !== '') {
                $article['description'] = $row['samenvatting'];
            }
            if ($row['image'] !== null) {
                $article['image'] = url($row['image_webp'] ?: $row['image']);
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => $article,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $url,
            'description' => $description,
            'inLanguage' => 'nl-NL',
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $site,
                'url' => url('/'),
            ],
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
                'numberOfItems' => count($items),
                'itemListElement' => $items,
            ],
        ];
    }
}
