# In de media

Publieke pagina: [`/in-de-media`](/in-de-media) («Gratis planten in de media»).
Bronvermeldingen: [`data/in-de-media.json`](../data/in-de-media.json) — geen DB-migratie voor de lijst.
Vaste kaartvorm: zie ook [`CONTEXT.md`](../CONTEXT.md).

De pagina heeft: korte intro, klikbare kaartjes (nieuwste eerst, links uitgelijnd),
blok «Voor redacties» (contact + kernfeiten) en een «Doe mee»-CTA.
Vindbaar via het hoofdmenu («In de media») en in de footerkolom «Over Gratis planten»
(direct onder «Over»).

Kernfeiten komen uit bestaande site-teksten (`config/site.php`, `/over`),
zonder exacte deelplekken- of bereikaantallen (die veranderen steeds).
Logo/OG-download alleen als `config('site.logo')` / `config('site.og_image')`
als bestand bestaat.

## Vaste kaarttekst

De tekst op een kaart is **geen** samenvatting van ons artikel. Vast patroon:

1. `over_medium` — kort wie het medium is, gebaseerd op hun eigen over-pagina
   (geen verzonnen feiten). Bron-URL in `over_medium_bron`.
2. Automatisch: «Op {datum} plaatste {medium} een artikel over Gratis planten
   met de kop «{kop}».» (`App\Services\InDeMedia::kaartTekst()`).
3. CTA: «Lees het artikel».

## Vermelding toevoegen

1. Open `data/in-de-media.json`.
2. Voeg onder `vermeldingen` een nieuw object toe (volgorde in het bestand maakt niet uit; de site sorteert op `datum`, nieuwste eerst):

```json
{
  "medium": "Naam van de krant of site",
  "datum": "2026-11-09",
  "kop": "Kop van het artikel",
  "over_medium": "Eén of twee zinnen over wie dit medium is (uit hun over-pagina).",
  "over_medium_bron": "https://voorbeeld.nl/over/",
  "url": "https://voorbeeld.nl/artikel/",
  "image": "/assets/images/in-de-media/voorbeeld.jpg",
  "image_webp": "/assets/images/in-de-media/voorbeeld.webp",
  "image_alt": "Korte beschrijving van de preview",
  "image_credit": "Naam van het medium"
}
```

3. Velden:
   - `medium` — mediumnaam (verplicht)
   - `datum` — ISO `YYYY-MM-DD` (verplicht; bepaalt sortering en `<time>`)
   - `kop` — titel op de kaart (verplicht)
   - `over_medium` — verplicht; 1–2 zinnen over het medium (eigen woorden, feiten van hun site)
   - `over_medium_bron` — aanbevolen; URL van de over-pagina waar `over_medium` op steunt
   - `url` — volledige `http(s)`-link (verplicht; hele kaart klikbaar, nieuw tabblad, `rel="noopener noreferrer"`)
   - `image` / `image_webp` — optioneel; lokaal pad onder `/assets/…` (jpg verplicht als je een beeld wilt; webp optioneel). Bij voorkeur de `og:image` van het artikel, lokaal opgeslagen (geen hotlink). Ontbreekt het bestand of het veld, dan toont de kaart een typografisch accent met de mediumnaam.
   - `image_alt` — alt-tekst (aanbevolen bij beeld; valt terug op de kop)
   - `image_credit` — bronvermelding onder de preview (aanbevolen; valt terug op `medium`)
4. Preview opslaan: download de `og:image`, resize naar max. ~960px breed, leg jpg (+ webp) in `public/assets/images/in-de-media/`, noteer bron/licentie in `docs/afbeeldingen-licenties.md`.
5. Check lokaal: `php artisan test --filter=InDeMedia`.
6. Commit + PR naar `staging`. Bump `VERSION` / `CHANGELOG.md` zoals bij andere contentreleases.

Ongeldige rijen (lege verplichte velden, foute datum, geen http(s)-URL) worden overgeslagen.
