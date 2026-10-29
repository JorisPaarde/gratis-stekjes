# Kennisbank URL-structuur

Keuze om botsing met soort-slugs te vermijden:

| Pad | Inhoud |
|-----|--------|
| `/stektips` | Overzicht: artikelen + 48 soortpagina’s, filterbaar/zoekbaar |
| `/stektips/artikel/{slug}` | Kennisbank-artikel (JSON in `data/kennisbank/*.json`) |
| `/stektips/{slug}` | Soortpagina (bestaand; data in `data/stektips.json` / `soorten/*.json`) |

Het padsegment `artikel` is gereserveerd en mag geen soort-slug zijn. Artikelslugs staan centraal in de JSON (`slug`, `titel`, `zoekwoorden`) zodat SEO-titels later eenvoudig aanpasbaar zijn (o.a. wanneer `/workspace/gp/stektips-keywords.md` beschikbaar is).

Gerelateerde blokken: artikelen delen categorieën/soorten; soortpagina’s tonen relevante artikelen via `gerelateerde_soorten`.
