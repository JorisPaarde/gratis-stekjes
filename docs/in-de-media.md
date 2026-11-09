# In de media

Publieke pagina: [`/in-de-media`](/in-de-media) («Gratis planten in de media»).
Bron: [`data/in-de-media.json`](../data/in-de-media.json) — geen database of migratie.
De pagina toont alleen de paginatitel en klikbare kaartjes (nieuwste eerst).

## Vermelding toevoegen

1. Open `data/in-de-media.json`.
2. Voeg onder `vermeldingen` een nieuw object toe (volgorde in het bestand maakt niet uit; de site sorteert op `datum`, nieuwste eerst):

```json
{
  "medium": "Naam van de krant of site",
  "datum": "2026-11-09",
  "kop": "Kop van het artikel",
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
   - `url` — volledige `http(s)`-link (verplicht; hele kaart klikbaar, nieuw tabblad, `rel="noopener noreferrer"`)
   - `samenvatting` — optioneel; alleen als één korte regel rustig op de kaart past
   - `image` / `image_webp` — optioneel; lokaal pad onder `/assets/…` (jpg verplicht als je een beeld wilt; webp optioneel). Bij voorkeur de `og:image` van het artikel, lokaal opgeslagen (geen hotlink). Ontbreekt het bestand of het veld, dan toont de kaart een typografisch accent met de mediumnaam.
   - `image_alt` — alt-tekst (aanbevolen bij beeld; valt terug op de kop)
   - `image_credit` — bronvermelding onder de preview (aanbevolen; valt terug op `medium`)
4. Preview opslaan: download de `og:image`, resize naar max. ~960px breed, leg jpg (+ webp) in `public/assets/images/in-de-media/`, noteer bron/licentie in `docs/afbeeldingen-licenties.md`.
5. Check lokaal: `php artisan test --filter=InDeMedia`.
6. Commit + PR naar `staging`. Bump `VERSION` / `CHANGELOG.md` zoals bij andere contentreleases.

Ongeldige rijen (lege verplichte velden, foute datum, geen http(s)-URL) worden overgeslagen.
