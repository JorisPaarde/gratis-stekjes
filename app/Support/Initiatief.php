<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Initiatiefpagina van een organisatie, uit data/organisaties.json («initiatief»).
 * Alleen-lezen: pagina's worden in de JSON bewerkt, niet in beheer.
 */
final class Initiatief
{
    /** @param list<array<string, mixed>> $secties */
    public function __construct(
        public readonly string $organisatie_slug,
        public readonly ?string $initiatief_slug,
        public readonly string $pagina_titel = '',
        public readonly string $intro_titel = '',
        public readonly string $intro_tekst = '',
        public readonly string $praktisch_tekst = '',
        public readonly string $ruilen_tekst = '',
        public readonly string $korte_naam = '',
        public readonly string $aanmeld_param = '',
        public readonly string $aanmeld_label = '',
        public readonly string $site_url = '',
        public readonly string $site_link_tekst = '',
        public readonly string $agenda_url = '',
        public readonly string $acties_leeg_tekst = '',
        public readonly string $leden_titel = '',
        public readonly string $leden_intro = '',
        public readonly string $kopieertekst = '',
        public readonly string $meta_description = '',
        public readonly array $secties = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(string $organisatieSlug, array $data): self
    {
        $str = static fn (string $key): string => trim((string) ($data[$key] ?? ''));
        $slug = $str('slug');

        return new self(
            organisatie_slug: $organisatieSlug,
            initiatief_slug: $slug !== '' ? $slug : null,
            pagina_titel: $str('pagina_titel'),
            intro_titel: $str('intro_titel'),
            intro_tekst: $str('intro_tekst'),
            praktisch_tekst: $str('praktisch_tekst'),
            ruilen_tekst: $str('ruilen_tekst'),
            korte_naam: $str('korte_naam'),
            aanmeld_param: $str('aanmeld_param'),
            aanmeld_label: $str('aanmeld_label'),
            site_url: $str('site_url'),
            site_link_tekst: $str('site_link_tekst'),
            agenda_url: $str('agenda_url'),
            acties_leeg_tekst: $str('acties_leeg_tekst'),
            leden_titel: $str('leden_titel'),
            leden_intro: $str('leden_intro'),
            kopieertekst: $str('kopieertekst'),
            meta_description: $str('meta_description'),
            secties: array_values(array_filter(
                is_array($data['secties'] ?? null) ? $data['secties'] : [],
                'is_array'
            )),
        );
    }

    public function heeftPagina(): bool
    {
        return $this->initiatief_slug !== null;
    }

    public function publiekeUrl(): ?string
    {
        return $this->heeftPagina() ? url('/'.$this->initiatief_slug) : null;
    }

    /** @return list<string> */
    public function introAlineas(): array
    {
        return self::alineas($this->intro_tekst);
    }

    /** @return list<string> */
    public function praktischAlineas(): array
    {
        return self::alineas($this->praktisch_tekst);
    }

    /** @return list<string> */
    public function ruilenAlineas(): array
    {
        return self::alineas($this->ruilen_tekst);
    }

    /** @return list<string> */
    private static function alineas(string $tekst): array
    {
        if ($tekst === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            preg_split("/\n\s*\n/", $tekst) ?: []
        ), static fn (string $a): bool => $a !== ''));
    }
}
