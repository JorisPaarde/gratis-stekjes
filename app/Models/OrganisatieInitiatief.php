<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganisatieInitiatief extends Model
{
    protected $table = 'organisatie_initiatieven';

    protected $fillable = [
        'organisatie_slug',
        'initiatief_slug',
        'pagina_titel',
        'intro_titel',
        'intro_tekst',
        'korte_naam',
        'aanmeld_param',
        'aanmeld_label',
        'site_url',
        'site_link_tekst',
        'agenda_url',
        'secundaire_link_label',
        'acties_leeg_tekst',
        'leden_titel',
        'leden_intro',
        'kopieertekst',
        'meta_description',
    ];

    public function heeftPagina(): bool
    {
        return $this->initiatief_slug !== null && trim((string) $this->initiatief_slug) !== '';
    }

    public function publiekeUrl(): ?string
    {
        if (! $this->heeftPagina()) {
            return null;
        }

        return url('/'.$this->initiatief_slug);
    }

    public function scopeMetPagina(Builder $query): Builder
    {
        return $query->whereNotNull('initiatief_slug')->where('initiatief_slug', '!=', '');
    }

    /** Intro als lijst van alinea’s (lege regels scheiden). */
    public function introAlineas(): array
    {
        $tekst = trim((string) $this->intro_tekst);
        if ($tekst === '') {
            return [];
        }

        $delen = preg_split("/\n\s*\n/", $tekst) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $p): string => trim($p),
            $delen
        ), static fn (string $p): bool => $p !== ''));
    }
}
