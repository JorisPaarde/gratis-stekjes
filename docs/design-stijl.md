# Designstijl — Gratis planten

Visuele richting: warm, organisch, activistisch-vriendelijk. Lettertypes en sectieritme blijven geïnspireerd op meerbomen.nu; **kleuren zijn eigen** (geen teal/peach/rood). Geen overname van hun logo, teksten of foto’s.

## Kleurenpalet (v0.11.2)

| Token | Hex | Rol |
|-------|-----|-----|
| `--forest` / `--leaf` | `#1F4D2B` | Koppen, hero-titelblok, donkere vlakken, CTA-paneel |
| `--forest-deep` | `#163820` | Hover / diepere bosgroen |
| `--mint` / `--bg-mint` / `--bg-2` | `#E6F2E1` | Lichte achtergronden en sectievlakken |
| `--orange` | `#E8833A` | CTA’s, knoppen, warn-card linkerrand/icoon |
| `--orange-hover` | `#D4742E` | Hover op oranje knoppen |
| `--orange-text` | `#1A1210` | Tekst op oranje (AA: ≈6.8:1 op `#E8833A`) |
| `--ink` | `#1A1210` | Bodytekst |
| `--ink-soft` | `#3D4A3F` | Secundaire tekst |
| `--accent-text` | `#8A4514` | “binnenkort” e.d. op licht |
| `--accent-on-dark` | `#FFE2B8` | Accent op bosgroen paneel |
| `--warn-bg` | `#FFFFFF` | Waarschuwingsblok op mint (oranje linkerrand) |
| `--bg` | `#FFFFFF` | Pagina-achtergrond |
| `--card` | `#FFFFFF` | Kaarten/formulieren |

**Theme / PWA:** `theme_color` `#1F4D2B`, `background_color` `#E6F2E1`.

### Hero-titelblok

Bosgroen `#1F4D2B` met **witte** tekst + lichte mint-rand. Contrast wit op bosgroen ≈ **9.75:1** (AA). Oranje titelblok met bosgroene tekst haalde AA niet (≈3.6:1); oranje blijft gereserveerd voor knoppen.

### Knoppen

Oranje `#E8833A` met **donkere** tekst `#1A1210` (wit op oranje ≈2.7:1 — onder AA). Focus: outline bosgroen.

## Typografie

Self-hosted OFL: **Passion One** (display) + **Titillium Web** (body). Zie `docs/afbeeldingen-licenties.md`.

## Secties & patronen

1. Sticky witte header; bosgroene nav; oranje pill-CTA.
2. Full-bleed hero (foto + bosgroene scrim), merk in bosgroen titelblok, korte lead, CTA’s.
3. Afwisselend wit / mint / donker bosgroen CTA-paneel; golf-SVG-scheiders.
4. Genummerde stappen; pill-knoppen; iconen via `<x-icon name="…" />`.

## Iconen

Lucide (ISC) + eigen planticonen (zelfde 24px-grid, stroke 2). Overzicht: `/stijlgids` (noindex).

## Motion & a11y

`prefers-reduced-motion` gerespecteerd; basistoestand zichtbaar zonder JS; WCAG AA voor tekstparen (tests in `HomepageContrastCssTest`).
