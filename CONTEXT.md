# Context voor agents

Korte afspraken die steeds terugkomen. Geen secrets.

## `/in-de-media` — vaste kaartvorm

Elke mediakaart volgt **dezelfde opbouw** (niet meer een samenvatting van ons artikel):

1. **Wie is het medium?** — 1–2 zinnen, gebaseerd op hun eigen over-pagina
   (veld `over_medium`). Verzin geen feiten; noteer de bron-URL in
   `over_medium_bron`.
2. **Dat zij over Gratis planten schreven** — automatisch samengesteld in
   `App\Services\InDeMedia::kaartTekst()` uit `datum` + `medium` + `kop`:
   «Op {datum} plaatste {medium} een artikel over Gratis planten met de kop «{kop}».»
3. CTA blijft **«Lees het artikel»** (hele kaart is de link).

Data: `data/in-de-media.json`. Uitleg voor content: `docs/in-de-media.md`.

## Deelplekken — geen aantallen

Noem nergens op de publieke site een exact aantal deelplekken (ook geen
dynamische «78 deelplekken» of typechips met tellers). Gebruik formuleringen
als «steeds meer deelplekken» of «elke week komen er nieuwe deelplekken bij».
Aantallen van aanbod/planten/leden/stadspagina’s mogen wel.
