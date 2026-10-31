# Afbeeldingen- en fontlicenties

Alle assets in deze repo voor de publieke site. Geen geheimen of serverpaden.

## Fonts (self-hosted)

| Bestand(en) | Bron | Licentie |
|-------------|------|----------|
| `public/fonts/passion-one-*.woff2` | [Passion One](https://fonts.google.com/specimen/Passion+One) | SIL Open Font License 1.1 |
| `public/fonts/titillium-web-*.woff2` | [Titillium Web](https://fonts.google.com/specimen/Titillium+Web) | SIL Open Font License 1.1 |

CSS: `@font-face` in `resources/css/fonts.css`. Geen Google Fonts CDN.

## Foto’s

| Bestand(en) | Bron | Auteur | Licentie |
|-------------|------|--------|----------|
| `public/assets/images/hero/hero-stekjes-{640,1280,1920}.webp` | [Pexels #4503273](https://www.pexels.com/photo/4503273/) (jonge planten in terracotta potten) | Anna Avilova (EXIF/Artist) | [Pexels License](https://www.pexels.com/license/) — gratis commercieel/niet-commercieel gebruik, geen toestemming vereist; geen herverkoop van de foto als product |
| `public/assets/images/home-v2/*` (homepage) | Pexels + Wikimedia Commons (self-hosted; geen hotlink) | zie `resources/data/foto-credits.json` (maker + bron-URL + licentie per id) | Pexels License of CC BY-SA 4.0; CC BY-SA zichtbaar toegeschreven op de homepage |
| `public/assets/images/soorten/*.jpg` (48 stektips-soorten) | Wikimedia Commons (self-hosted; geen hotlink) | zie `data/soort-afbeeldingen.json` (auteur + bron-URL per bestand) | alleen CC0 / Public Domain / CC BY / CC BY-SA — per bestand geverifieerd; zichtbare attribution bij de afbeelding |

Geen foto’s van meerbomen.nu. Geen hotlinking. Herdownloaden/verversen: `php scripts/fetch-soort-afbeeldingen.php`.

## Illustraties (eigen werk)

| Bestand | Beschrijving | Licentie |
|---------|--------------|----------|
| `public/assets/illustrations/hero-planten.svg` | Fallback/decoratieve hero-SVG in bosgroen/mint/oranje | CC0 / Unlicense |
| `public/assets/illustrations/wave-divider.svg` | Golfscheiding naar wit | CC0 / Unlicense |
| `public/assets/illustrations/wave-divider-mint.svg` | Golfscheiding mint | CC0 / Unlicense |
| `public/assets/illustrations/step-stekje.svg` | Stekje-icoon | CC0 / Unlicense |
| `public/assets/illustrations/soort-fallback.svg` | Neutrale fallback wanneer een soort geen geverifieerde foto heeft | CC0 / Unlicense |

## Iconen

| Bron | Bestanden | Licentie |
|------|-----------|----------|
| [Lucide](https://lucide.dev) (subset) | paden in `resources/views/components/icon.blade.php` | ISC — volledige tekst in `docs/LICENSE-LUCIDE.md` |
| Eigen planticonen (`stekje`, `plant`, `zaad`, `ophalen`, …) |zelfde component | CC0 / Unlicense — gemaakt voor dit project |
