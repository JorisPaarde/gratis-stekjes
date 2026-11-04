<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrganisatieInitiatief;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class InitiatiefPaginas
{
    /**
     * Top-level paden die niet als initiatief-slug mogen (routes + bekende prefixes).
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [
            'privacy',
            'contact',
            'ervaringen',
            'initiatieven',
            'deelplekken',
            'voor-organisaties',
            'organisaties',
            'partners',
            'stijlgids',
            'robots.txt',
            'sitemap.xml',
            'manifest.webmanifest',
            'hoe-werkt-het',
            'hulpmiddelen',
            'over',
            'doe-mee',
            'volkstuinen',
            'help-mee',
            'poster',
            'stekjeslabels',
            'natuur',
            'verbinding',
            'invasieve-soorten',
            'vermeerderen',
            'stektips',
            'gratis-planten',
            'aanbod',
            'aanbieden',
            'api',
            'cron',
            'deploy',
            'account',
            'beheer',
            'feedback',
            'berichtmail',
            'naam-check',
            'media',
            'assets',
            'build',
            'vendor',
            'storage',
            'livewire',
            'up',
            'horizon',
            'telescope',
        ];
    }

    public static function beschikbaar(): bool
    {
        return Schema::hasTable('organisatie_initiatieven');
    }

    public function findByInitiatiefSlug(string $slug): ?OrganisatieInitiatief
    {
        if (! self::beschikbaar() || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }

        return OrganisatieInitiatief::query()
            ->metPagina()
            ->where('initiatief_slug', $slug)
            ->first();
    }

    public function findByOrganisatieSlug(string $slug): ?OrganisatieInitiatief
    {
        if (! self::beschikbaar() || $slug === '') {
            return null;
        }

        return OrganisatieInitiatief::query()
            ->where('organisatie_slug', $slug)
            ->first();
    }

    public function findByAanmeldParam(string $param): ?OrganisatieInitiatief
    {
        if (! self::beschikbaar() || $param === '' || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $param)) {
            return null;
        }

        return OrganisatieInitiatief::query()
            ->metPagina()
            ->where('aanmeld_param', $param)
            ->first();
    }

    /** @return list<OrganisatieInitiatief> */
    public function metPagina(): array
    {
        if (! self::beschikbaar()) {
            return [];
        }

        return OrganisatieInitiatief::query()->metPagina()->orderBy('korte_naam')->get()->all();
    }

    /** Publieke URL voor een organisatie-rij (initiatiefpagina of /organisaties/{slug}). */
    public function publiekeUrlVoorOrganisatie(string $organisatieSlug): string
    {
        $init = $this->findByOrganisatieSlug($organisatieSlug);
        if ($init !== null && $init->heeftPagina()) {
            return route('initiatief.show', ['slug' => $init->initiatief_slug]);
        }

        return route('organisaties.show', ['slug' => $organisatieSlug]);
    }

    /**
     * Valideer initiatief_slug: leeg OK, anders lowercase a-z0-9-, niet reserved, uniek.
     *
     * @throws ValidationException
     */
    public function assertSlugAllowed(?string $slug, ?int $ignoreId = null): void
    {
        $slug = $slug === null ? '' : trim($slug);
        if ($slug === '') {
            return;
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages([
                'initiatief_slug' => 'De slug mag alleen kleine letters, cijfers en streepjes bevatten (bijv. struikroven).',
            ]);
        }

        if (in_array($slug, self::reservedSlugs(), true)) {
            throw ValidationException::withMessages([
                'initiatief_slug' => 'Deze slug botst met een bestaande pagina op de site.',
            ]);
        }

        $query = OrganisatieInitiatief::query()->where('initiatief_slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'initiatief_slug' => 'Deze slug is al in gebruik bij een ander initiatief.',
            ]);
        }
    }

    /**
     * Valideer aanmeld_param: leeg OK (geen vinkje), anders uniek per initiatief.
     *
     * @throws ValidationException
     */
    public function assertAanmeldParamAllowed(?string $param, ?int $ignoreId = null): void
    {
        $param = $param === null ? '' : trim($param);
        if ($param === '') {
            return;
        }

        if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $param)) {
            throw ValidationException::withMessages([
                'aanmeld_param' => 'De aanmeld-parameter mag alleen kleine letters, cijfers en underscores bevatten, beginnend met een letter.',
            ]);
        }

        $query = OrganisatieInitiatief::query()->where('aanmeld_param', $param);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'aanmeld_param' => 'Deze aanmeld-parameter is al in gebruik.',
            ]);
        }
    }
}
