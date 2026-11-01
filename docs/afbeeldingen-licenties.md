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

## Social images (`public/assets/og/`)

Open Graph-afbeeldingen (1200×630) voor delen op WhatsApp, LinkedIn, Facebook enz. Gegenereerd met
`node scripts/og-images.mjs` (Playwright nodig; zie kop van het script).

| Bestand | Foto | Licentie |
|---|---|---|
| `standaard.jpg`, `home.jpg`, `hoe-werkt-het.jpg`, `stektips.jpg` | home-v2 Pexels-foto's (delen, stekjes, aanmelden) | Pexels License |
| `aanbod.jpg`, `deelplekken.jpg` | kaartvoorbeeld (home-v2 `kaart`) | ODbL, «© OpenStreetMap-bijdragers» staat in de afbeelding |
| `gratis-planten.jpg`, `stad/*.jpg` | geen foto (eigen icoon) | eigen werk |
| `soort/*.jpg` | soortfoto uit `data/soort-afbeeldingen.json` | licentie van de foto (meestal CC BY-SA); maker + licentie staan zichtbaar in de afbeelding. Als bewerking vallen deze afbeeldingen onder dezelfde licentie. |

Nieuwe soort of stad? Draai `node scripts/og-images.mjs soort` of `… stad`. Een test controleert dat elke stektip en
elke stad een eigen afbeelding heeft.

## Badges

`public/assets/badges/*.svg` zijn eigen ontwerpen voor Gratis planten (1 nov 2026), in de
huisstijlkleuren. Geen externe bronnen; vrij te gebruiken onder dezelfde licentie als de site.
