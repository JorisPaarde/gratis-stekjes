<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Initiatief;

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

    /** Initiatieven komen uit data/organisaties.json; altijd beschikbaar. */
    public static function beschikbaar(): bool
    {
        return true;
    }

    /** @var array<string, Initiatief>|null keyed by organisatie-slug */
    private ?array $cache = null;

    public function __construct(private readonly Organisaties $organisaties) {}

    /** @return array<string, Initiatief> */
    private function alle(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $this->cache = [];
        foreach ($this->organisaties->all() as $org) {
            $data = $org['initiatief'] ?? null;
            if (is_array($data) && isset($org['slug'])) {
                $this->cache[(string) $org['slug']] = Initiatief::fromArray((string) $org['slug'], $data);
            }
        }

        return $this->cache;
    }

    public function findByInitiatiefSlug(string $slug): ?Initiatief
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        foreach ($this->alle() as $init) {
            if ($init->initiatief_slug === $slug) {
                return $init;
            }
        }

        return null;
    }

    public function findByOrganisatieSlug(string $slug): ?Initiatief
    {
        return $slug === '' ? null : ($this->alle()[$slug] ?? null);
    }

    public function findByAanmeldParam(string $param): ?Initiatief
    {
        if ($param === '' || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $param)) {
            return null;
        }
        foreach ($this->metPagina() as $init) {
            if ($init->aanmeld_param === $param) {
                return $init;
            }
        }

        return null;
    }

    /** @return list<Initiatief> */
    public function metPagina(): array
    {
        $lijst = array_values(array_filter($this->alle(), static fn (Initiatief $i): bool => $i->heeftPagina()));
        usort($lijst, static fn (Initiatief $a, Initiatief $b): int => strcmp($a->korte_naam, $b->korte_naam));

        return $lijst;
    }

    /** Publieke URL voor een organisatie (initiatiefpagina of /organisaties/{slug}). */
    public function publiekeUrlVoorOrganisatie(string $organisatieSlug): string
    {
        $init = $this->findByOrganisatieSlug($organisatieSlug);
        if ($init !== null && $init->heeftPagina()) {
            return route('initiatief.show', ['slug' => $init->initiatief_slug]);
        }

        return route('organisaties.show', ['slug' => $organisatieSlug]);
    }

    /**
     * Controleer de JSON: slugs geldig, niet gereserveerd en uniek; aanmeld-params uniek.
     *
     * @return list<string>
     */
    public function validate(): array
    {
        $errors = [];
        $slugs = [];
        $params = [];
        foreach ($this->alle() as $org => $init) {
            $slug = $init->initiatief_slug;
            if ($slug !== null) {
                if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                    $errors[] = "$org: initiatief.slug ongeldig";
                } elseif (in_array($slug, self::reservedSlugs(), true)) {
                    $errors[] = "$org: initiatief.slug $slug botst met een bestaande pagina";
                } elseif (isset($slugs[$slug])) {
                    $errors[] = "$org: initiatief.slug $slug dubbel";
                }
                $slugs[$slug] = true;
            }
            $param = $init->aanmeld_param;
            if ($param !== '') {
                if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $param)) {
                    $errors[] = "$org: initiatief.aanmeld_param ongeldig";
                } elseif (isset($params[$param])) {
                    $errors[] = "$org: initiatief.aanmeld_param $param dubbel";
                }
                $params[$param] = true;
            }
        }

        return $errors;
    }
}
