# Designstijl — Gratis planten

Visuele richting geïnspireerd op [meerbomen.nu](https://meerbomen.nu) (kleuren, typografie, sectiestructuur, spacing). **Geen** overname van hun logo, teksten, foto’s of illustraties.

Referentie bekeken: homepage + o.a. Organisatie / Over ons (Elementor + Hello child theme). Onze tokens zijn AA-aangepast waar hun ruwe merkkleuren te licht waren voor witte tekst.

## Gevoel

Warm, activistisch-vriendelijk, organisch. Witte rustvlakken, afwisselende volle kleurvlakken (teal / coral / peach), diagonale of golfvormige sectie-overgangen, pill-knoppen, stevige display-koppen.

## Kleurenpalet (CSS-tokens in `resources/css/site.css`)

| Token | Hex | Rol |
|-------|-----|-----|
| `--bg` | `#FFFFFF` | Pagina-achtergrond |
| `--bg-2` | `#F7F3EE` | Crème-vlak / footer |
| `--bg-teal` | `#E6F3F4` | Lichte teal-sectie |
| `--ink` | `#1A1210` | Bodytekst (bijna-zwart, warm) |
| `--ink-soft` | `#4A4540` | Secundaire tekst |
| `--teal` | `#1F6B74` | Merk-teal (gedonkerd t.o.v. ~`#349BA8` voor AA met wit) |
| `--teal-deep` | `#16555C` | Hover / diepe teal |
| `--coral` | `#B84F40` | Primaire CTA (gedonkerd t.o.v. ~`#DD7765` / `#DD7F65` voor AA met wit) |
| `--coral-deep` | `#9A3F32` | Coral hover / tekstaccent op licht |
| `--peach` | `#F4CFC2` | Warme accentvlakken |
| `--sage` | `#5A5B42` | Secundair groen/sage |
| `--accent-text` | `#9A3F32` | “binnenkort” e.d. op lichte achtergrond |
| `--accent-on-dark` | `#FFE2B8` | Accent op teal-paneel |
| `--card` | `#FFFFFF` | Kaarten/formulieren |
| `--line` | `rgba(26, 18, 16, 0.12)` | Randen |
| `--warn` | `#7A3B12` | Waarschuwingstekst |
| `--ok` / `--danger` | `#1F5A38` / `#8B1E1E` | Status |

**Theme / PWA:** `theme_color` = `#1F6B74`, `background_color` = `#FFFFFF`.

Alle body-/UI-tekstparen zijn bedoeld voor **WCAG AA** (≥ 4.5:1). Tests in `tests/Feature/HomepageContrastCssTest.php`.

## Typografie

Zelfde families als meerbomen.nu (Elementor laadt ze lokaal bij hen). Bij ons **self-hosted** onder `/public/fonts/` (OFL), geen Google Fonts CDN.

| Rol | Family | Gebruik |
|-----|--------|---------|
| Display | **Passion One** (400/700) | Hero-merk, sectiekoppen, titelblokken |
| Body | **Titillium Web** (400/600/700) | Navigatie, body, knoppen, formulieren |

Fallback: `Georgia, serif` (display) / `Segoe UI, sans-serif` (body).

## Paginastructuur

1. **Header** — witte sticky bar, teal nav-links, coral pill-CTA (“Doe mee”).
2. **Hero** — full-bleed visueel vlak (eigen SVG-patroon), merknaam als hero-signaal in coral titelblok (wit op coral), korte lead, CTA-groep. Geen stats/kaartjes in het eerste viewport.
3. **Secties** — afwisselend wit / licht-teal / peach / teal-paneel; optionele SVG-golf of diagonale clip tussen blokken.
4. **Kaarten** — alleen waar interactie of een discrete inhoudsgroep dat vraagt; lichte schaduw, matige radius (~0.75–1rem), geen “dashboard”-grid in de hero.
5. **Knoppen** — pill (`border-radius: 999px`), filled coral of teal, outline-variant op lichte vlakken; uppercase + letter-spacing voor primaire CTA’s (meerbomen-achtig).
6. **Hoe-werkt-het** — genummerde stappen (cirkelbadges), geen zware card-borders verplicht.
7. **Footer** — crème ondergrond, korte links, versie.

## Motion

- Subtiele `rise`-entrance op hero/kaarten/CTA **alleen** bij `prefers-reduced-motion: no-preference`.
- Bij `reduce`: geen animaties/transitions; zichtbaarheid blijft `opacity: 1`.
- Pagina leesbaar zonder JavaScript.

## Wat we bewust niet overnemen

- Logo, woordmerken, fotografie en illustraties van MeerBomenNu.
- Hun copy en campagneteksten.
- Exacte Elementor-widgetstructuur; wel het *ritme* (kleurvlakken, pills, display-type).
