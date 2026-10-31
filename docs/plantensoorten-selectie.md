# Selectiecriteria plantensoorten-catalogus

Doel: autocomplete, suggestielijsten en soortpagina-index tonen alleen soorten die
**hobbyisten in Nederland realistisch kunnen vermeerderen en gratis delen**, plus
typische **voedselbos**-soorten. Records worden niet verwijderd: bestaande
`aanbod.plantensoort_id`-koppelingen blijven werken.

Bronbestand: `data/plantensoorten.json`. Runtime: `App\Services\Plantensoorten`.

## Zichtbaar (`zichtbaar: true`, default)

Een soort blijft in lijsten/autocomplete als minstens één van deze geldt:

1. **Hobby-vermeerdering** — stek (kop-/stengel-/blad-/houtstek), scheuren,
   uitlopers/runners, offsets/pups, of gangbaar zaad delen (moestuin, kruiden).
2. **Voedselbos** — fruit-/nootstruiken en -bomen, meerjarige groenten, kruiden,
   eetbare bodembedekkers, stikstofbinders en typische gilde-/chop-and-drop-planten
   (tag `voedselbos`).
3. **Stektips-pagina** — soorten met `slug` naar `/stektips/{slug}` blijven altijd
   zichtbaar (inhoud + SEO long-tail `{plant} stekken`).

## Verborgen (`zichtbaar: false`)

Verborgen (niet gesuggereerd, wel vindbaar via `find`/`exists`/`resolveExact`):

| Categorie | Voorbeelden | Reden |
|-----------|-------------|--------|
| Palmen & cycaden | Bergpalm, Kentiapalm, Vederpalm | Geen realistische hobby-stekjescultuur |
| Specialist-orchideeën | Dendrobium, Cymbidium | Lab/keiki zeldzaam; Phalaenopsis blijft (keiki’s) |
| Specialist/traag | Lithops, Olifantenvoet, Schoonmoederstoel, Kamer-cyclaam | Zelden gedeeld op stekjesruilen |
| Moeilijke sierbomen | Magnolia, Japanse sierkers | Cultivar/enting, geen voedselbos |
| Traag haagmateriaal | Taxus, Beukenhaag, Haagbeuk, Hulst | Geen stekjes-deelcultuur |
| Eenjarige sierbedden | Petunia, Cosmea, Zinnia, Lobelia, … | Geen stekjes-/voedselbosfocus (zaadbedden) |

**Niet** verbergen via deze lijst: EU-Unielijst invasieve exoten. Die blijven in
`data/niet-aanbieden.json` en worden geblokkeerd bij aanbieden
(`GeenInvasieveSoort` / `InvasieveSoorten`). Catalogus en Unielijst overlappen niet.

## Tag `voedselbos`

Optioneel veld `tags: ["voedselbos"]`. Filter: `GET /api/plantensoorten?q=…&tag=voedselbos`.

Getagd o.a.: alle `categorie` eetbaar/kruid, fruit/noten, meerjarige groente,
tuinkruiden, bessen, en gilde-planten (smeerwortel, brandnetel, wilg, lupine, …).

## Meta

`meta.aantal` = totaal records; `meta.zichtbaar` / `meta.verborgen` / `meta.voedselbos`
voor tellingen na selectie.
