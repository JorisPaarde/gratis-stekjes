<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AanbodSearch;
use App\Services\NuTeStekken;
use App\Services\Plantensoorten;
use App\Services\Stektips;
use App\Services\ZoekIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ZoekenController extends Controller
{
    public function __invoke(Request $request, NuTeStekken $calendar, AanbodSearch $search, Stektips $stektips, Plantensoorten $plantensoorten, ZoekIntent $intent): View|RedirectResponse
    {
        // Eén zoekveld (hero): herken plaats, postcode, plant of allebei en stuur door naar
        // de bestaande ?q= en ?plant=. Onbekende invoer krijgt hieronder een nette lege staat.
        $zoekOnbekend = null;
        if ($request->has('zoek')) {
            $uitkomst = $intent->bepaal((string) $request->query('zoek', ''), $this->magGeocoderen($request));
            if ($uitkomst['soort'] === ZoekIntent::ONBEKEND) {
                $zoekOnbekend = ['tekst' => $uitkomst['tekst'], 'suggesties' => $uitkomst['suggesties']];
            } else {
                return redirect()->route('aanbod', array_filter([
                    'q' => $uitkomst['q'],
                    'plant' => $uitkomst['plant'],
                ], static fn ($v) => $v !== null && $v !== ''));
            }
        }

        $plant = trim((string) $request->query('plant', ''));
        if (mb_strlen($plant) > 80) {
            $plant = mb_substr($plant, 0, 80);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > 120) {
            $q = mb_substr($q, 0, 120);
        }

        $plantItems = [];
        $plantSortering = null;
        if ($plant !== '' && $q === '') {
            // Alleen het 2e veld: dichtstbij het eigen adres van het ingelogde lid, anders nieuwste eerst.
            $user = $request->user();
            $plantSortering = AanbodSearch::plantSortering($user);
            $plantItems = $search->zoekOpPlant($plant, null, AanbodSearch::accountOrigin($user));
        } elseif ($plant !== '') {
            $plantItems = $search->search(null, null, false, $plant)['items'];
        }

        // Zoekmeting alleen via /api/zoeken (productkeuze A: API-only).

        $deelwoord = $plant !== ''
            ? $this->aanbodZelfstandigNaamVoorPlant($plant, $stektips, $plantensoorten)
            : 'stekjes';
        $title = $plant !== ''
            ? 'Gratis '.$plant.'-'.$deelwoord
            : 'Aanbod: gratis planten en stekjes ophalen';
        $heading = $plant !== ''
            ? 'Gratis '.$plant.'-'.$deelwoord
            : 'Gratis planten en stekjes ophalen';
        $metaDescription = $plant !== ''
            ? 'Gratis '.$plant.'-'.$deelwoord.' in Nederland en België. Zoek op postcode of plaats en haal ze op met een praatje.'
            : 'Gratis planten en stekjes ophalen: zoek op postcode of plaats, bekijk de kaart en haal ze zelf op. De enige tegenprestatie is een praatje.';

        return view('pages.aanbod', [
            'title' => $title,
            'heading' => $heading,
            'metaDescription' => $metaDescription,
            // Filter-URL's (?plant=) canonicaliseren naar de kale aanbodpagina.
            'canonical' => route('aanbod'),
            'nuTeStekken' => $calendar->blockForViews(),
            'plant' => $plant,
            'q' => $q,
            'plantItems' => $plantItems,
            'plantSortering' => $plantSortering,
            'zoekOnbekend' => $zoekOnbekend,
            'maakMeer' => $plant !== ''
                ? $this->maakMeerKaart($plant, $stektips, $plantensoorten, count($plantItems) > 0)
                : null,
        ]);
    }

    /** Maximaal 30 netwerkopzoekingen per uur per IP vanuit het hero-zoekveld (PDOK/Nominatim). */
    private function magGeocoderen(Request $request): bool
    {
        $key = 'hero-zoek:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return false;
        }
        RateLimiter::hit($key, 3600);

        return true;
    }

    /**
     * «stekjes» alleen als de gezochte plant een stektip heeft waarin stekken een methode is.
     * Alleen zaad → «zaad»; anders zonder stek → «plantjes».
     */
    private function aanbodZelfstandigNaamVoorPlant(string $plant, Stektips $stektips, Plantensoorten $plantensoorten): string
    {
        $soort = $stektips->voorZoekterm($plant, $plantensoorten);

        return $soort !== null ? $stektips->aanbodZelfstandigNaam($soort) : 'plantjes';
    }

    /**
     * Kaart bij een plantzoekopdracht: bestaande stektip-tekst (niets verzonnen) plus knop naar aanbieden.
     *
     * @return array{
     *     naam: string,
     *     heeft_aanbod: bool,
     *     heeft_methode: bool,
     *     kort: ?string,
     *     wanneer: ?string,
     *     stektip_url: ?string,
     *     aanbied_url: string
     * }
     */
    private function maakMeerKaart(string $plant, Stektips $stektips, Plantensoorten $plantensoorten, bool $heeftAanbod): array
    {
        $soort = $stektips->voorZoekterm($plant, $plantensoorten);
        $catalogus = $plantensoorten->resolveExact($plant);
        $naam = is_array($soort) && ! empty($soort['naam'])
            ? (string) $soort['naam']
            : (is_array($catalogus) && ! empty($catalogus['naam']) ? (string) $catalogus['naam'] : $plant);

        $kort = is_array($soort) ? trim((string) ($soort['kort'] ?? '')) : '';
        if ($kort === '' && is_array($soort)) {
            $kort = trim((string) ($soort['methode'] ?? ''));
        }
        $wanneer = is_array($soort) ? trim((string) ($soort['wanneer'] ?? '')) : '';
        $slug = is_array($soort) ? trim((string) ($soort['slug'] ?? '')) : '';

        return [
            'naam' => $naam,
            'heeft_aanbod' => $heeftAanbod,
            'heeft_methode' => $kort !== '',
            'kort' => $kort !== '' ? $kort : null,
            'wanneer' => $wanneer !== '' ? $wanneer : null,
            'stektip_url' => $slug !== '' ? route('stektips.show', $slug) : null,
            'aanbied_url' => route('aanbieden', ['plant' => $naam]),
        ];
    }
}
