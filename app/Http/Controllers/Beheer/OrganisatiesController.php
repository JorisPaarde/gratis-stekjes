<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\OrganisatieInitiatief;
use App\Services\InitiatiefPaginas;
use App\Services\Organisaties;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrganisatiesController extends Controller
{
    public function index(Organisaties $organisaties, InitiatiefPaginas $initiatieven): View
    {
        $rows = [];
        foreach ($organisaties->all() as $org) {
            $slug = (string) $org['slug'];
            $init = $initiatieven->findByOrganisatieSlug($slug);
            $rows[] = [
                'org' => $org,
                'init' => $init,
            ];
        }

        return view('beheer.organisaties', [
            'title' => 'Organisaties — initiatiefpagina’s',
            'metaDescription' => 'Beheer',
            'canonical' => route('beheer.organisaties'),
            'rows' => $rows,
        ]);
    }

    public function edit(string $organisatieSlug, Organisaties $organisaties, InitiatiefPaginas $initiatieven): View
    {
        $org = $organisaties->find($organisatieSlug);
        if ($org === null) {
            abort(404);
        }

        $init = $initiatieven->findByOrganisatieSlug($organisatieSlug)
            ?? new OrganisatieInitiatief([
                'organisatie_slug' => $organisatieSlug,
                'korte_naam' => (string) ($org['naam'] ?? ''),
                'pagina_titel' => (string) ($org['naam'] ?? ''),
                'intro_titel' => 'Wie zijn ze?',
                'site_url' => (string) ($org['website'] ?? ''),
                'site_link_tekst' => 'Meer over '.((string) ($org['naam'] ?? 'de organisatie')),
                'meta_description' => (string) ($org['meta_description'] ?? $org['kort'] ?? ''),
            ]);

        return view('beheer.organisatie-edit', [
            'title' => 'Initiatiefpagina: '.$org['naam'],
            'metaDescription' => 'Beheer',
            'canonical' => route('beheer.organisaties.edit', ['organisatieSlug' => $organisatieSlug]),
            'org' => $org,
            'init' => $init,
            'reservedHint' => implode(', ', array_slice(InitiatiefPaginas::reservedSlugs(), 0, 12)).', …',
        ]);
    }

    public function update(
        string $organisatieSlug,
        Request $request,
        Organisaties $organisaties,
        InitiatiefPaginas $initiatieven,
    ): RedirectResponse {
        if (! Schema::hasTable('organisatie_initiatieven')) {
            return back()->withErrors(['initiatief' => 'Database-tabel ontbreekt nog.']);
        }

        $org = $organisaties->find($organisatieSlug);
        if ($org === null) {
            abort(404);
        }

        $data = $request->validate([
            'initiatief_slug' => ['nullable', 'string', 'max:80'],
            'pagina_titel' => ['nullable', 'string', 'max:200'],
            'intro_titel' => ['nullable', 'string', 'max:200'],
            'intro_tekst' => ['nullable', 'string', 'max:10000'],
            'praktisch_tekst' => ['nullable', 'string', 'max:10000'],
            'ruilen_tekst' => ['nullable', 'string', 'max:10000'],
            'korte_naam' => ['nullable', 'string', 'max:120'],
            'aanmeld_param' => ['nullable', 'string', 'max:40'],
            'aanmeld_label' => ['nullable', 'string', 'max:120'],
            'site_url' => ['nullable', 'string', 'max:500'],
            'site_link_tekst' => ['nullable', 'string', 'max:160'],
            'agenda_url' => ['nullable', 'string', 'max:500'],
            'secundaire_link_label' => ['nullable', 'string', 'max:160'],
            'acties_leeg_tekst' => ['nullable', 'string', 'max:2000'],
            'leden_titel' => ['nullable', 'string', 'max:160'],
            'leden_intro' => ['nullable', 'string', 'max:2000'],
            'kopieertekst' => ['nullable', 'string', 'max:5000'],
            'meta_description' => ['nullable', 'string', 'max:400'],
        ], [
            'site_url.url' => 'Vul een geldige URL in (https://…).',
            'agenda_url.url' => 'Vul een geldige agenda-URL in (https://…).',
        ]);

        foreach (['site_url', 'agenda_url'] as $urlField) {
            $url = trim((string) ($data[$urlField] ?? ''));
            if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
                return back()->withInput()->withErrors([$urlField => 'URL moet met http:// of https:// beginnen.']);
            }
            $data[$urlField] = $url !== '' ? $url : null;
        }

        $slug = strtolower(trim((string) ($data['initiatief_slug'] ?? '')));
        $data['initiatief_slug'] = $slug !== '' ? $slug : null;

        $param = strtolower(trim((string) ($data['aanmeld_param'] ?? '')));
        $data['aanmeld_param'] = $param !== '' ? $param : null;

        $existing = $initiatieven->findByOrganisatieSlug($organisatieSlug);
        $ignoreId = $existing?->id;

        $initiatieven->assertSlugAllowed($data['initiatief_slug'], $ignoreId);
        $initiatieven->assertAanmeldParamAllowed($data['aanmeld_param'], $ignoreId);

        // Zonder slug: geen publieke pagina; rest van de velden mag wel bewaard.
        $payload = [
            'organisatie_slug' => $organisatieSlug,
            'initiatief_slug' => $data['initiatief_slug'],
            'pagina_titel' => trim((string) ($data['pagina_titel'] ?? '')),
            'intro_titel' => trim((string) ($data['intro_titel'] ?? '')),
            'intro_tekst' => trim((string) ($data['intro_tekst'] ?? '')),
            'praktisch_tekst' => trim((string) ($data['praktisch_tekst'] ?? '')),
            'ruilen_tekst' => trim((string) ($data['ruilen_tekst'] ?? '')),
            'korte_naam' => trim((string) ($data['korte_naam'] ?? '')),
            'aanmeld_param' => $data['aanmeld_param'],
            'aanmeld_label' => trim((string) ($data['aanmeld_label'] ?? '')),
            'site_url' => $data['site_url'],
            'site_link_tekst' => trim((string) ($data['site_link_tekst'] ?? '')) ?: null,
            'agenda_url' => $data['agenda_url'],
            'secundaire_link_label' => trim((string) ($data['secundaire_link_label'] ?? '')) ?: null,
            'acties_leeg_tekst' => trim((string) ($data['acties_leeg_tekst'] ?? '')) ?: null,
            'leden_titel' => trim((string) ($data['leden_titel'] ?? '')),
            'leden_intro' => trim((string) ($data['leden_intro'] ?? '')) ?: null,
            'kopieertekst' => trim((string) ($data['kopieertekst'] ?? '')) ?: null,
            'meta_description' => trim((string) ($data['meta_description'] ?? '')),
        ];

        if ($existing !== null) {
            $existing->fill($payload)->save();
        } else {
            OrganisatieInitiatief::query()->create($payload);
        }

        $status = $payload['initiatief_slug'] !== null
            ? 'Initiatiefpagina opgeslagen: /'.$payload['initiatief_slug']
            : 'Opgeslagen (geen initiatiefpagina — slug leeg).';

        return redirect()
            ->route('beheer.organisaties.edit', ['organisatieSlug' => $organisatieSlug])
            ->with('status', $status);
    }
}
