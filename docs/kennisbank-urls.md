# Kennisbank URL-structuur (v0.20)

## Padkeuze

We blijven onder `/stektips` (bestaande merk-/navigatie-URL). De SEO-keywords file stelde `/stekken/`, `/snoeien/` enz. voor; die paden zouden botsen met soort-slugs en de bestaande kennisbank. **Titels, H1 en meta volgen wel de hoofdtermen** uit het zoekwoordenonderzoek.

| Pad | Inhoud |
|-----|--------|
| `/stektips` | Overzicht met 5 pijlers + filters/zoek |
| `/stektips/artikel/{slug}` | Artikel of pijler-hub |
| `/stektips/{slug}` | Soortpagina (= stekpagina voor o.a. lavendel, hortensia, monstera, vijg) |
| `/aanbod?plant=X` | Aanbod gefilterd op plantnaam in tekst (`q` blijft postcode/plaats) |

Geen 301’s nodig in 0.20.0: bestaande artikelslugs uit 0.19.0 blijven geldig; categorieën zijn hernoemd naar de 5 pijlers.

## Vijf pijlers

1. **Stekken** → `/stektips/artikel/stekken`
2. **Snoeien** (+ snoeikalender) → `/stektips/artikel/snoeien`
3. **Natuurlijk bewortelen** → `/stektips/artikel/natuurlijk-bewortelen` (stekpoeder → wilgenwater)
4. **Permacultuur** → `/stektips/artikel/permacultuur-gilde-voedselbos` (bestaande hub-slug behouden)
5. **Vermeerderen overig** → `/stektips/artikel/vermeerderen`

## Mapping bestaande 11 artikelen → pijler

| Artikel | Pijler |
|---------|--------|
| stekken (nieuw hub), kopstek-en-scheutstek, bladstek, winterhoutstek, stekken-in-water-of-grond, wanneer-stekken, stekgrond-zelf-maken | Stekken |
| snoeien (nieuw), *-snoeien, snoeiafval-als-stek | Snoeien |
| natuurlijk-bewortelen, stekpoeder, wilgenwater | Natuurlijk bewortelen |
| permacultuur-gilde-voedselbos | Permacultuur |
| vermeerderen, scheuren-en-afleggen | Vermeerderen overig |

## Soorten (48) op het overzicht

Filterbaar op `/stektips`. Soorten met SEO-hub (`soort-hubs.json`): hortensia, lavendel, monstera, vijg — dienen als **stekpagina** (geen dubbele “X stekken”-artikelpagina).

## Afwijkingen t.o.v. `docs/seo/stektips-keywords.md`

- Geen `/stekken/`-rootpaden: behoud `/stektips/artikel/…` (botsing + bestaande links).
- `?plant=` i.p.v. `?q=` voor soortfilter op aanbod (`q` = geocoding).
- Geen aparte zaaien-pijler (randgebied in het onderzoek).
- `houtstek` niet als hoofdterm (navigatie-SERP volgens onderzoek).
- Permacultuur-hub-slug blijft `permacultuur-gilde-voedselbos` (geen breaking rename).
