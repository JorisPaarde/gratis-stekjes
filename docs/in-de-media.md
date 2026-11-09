# In de media

Publieke pagina: [`/in-de-media`](/in-de-media) («Gratis planten in de media»).
Bron: [`data/in-de-media.json`](../data/in-de-media.json) — geen database of migratie.

## Vermelding toevoegen

1. Open `data/in-de-media.json`.
2. Voeg onder `vermeldingen` een nieuw object toe (volgorde in het bestand maakt niet uit; de site sorteert op `datum`, nieuwste eerst):

```json
{
  "medium": "Naam van de krant of site",
  "datum": "2026-11-09",
  "kop": "Kop van het artikel",
  "samenvatting": "Eén of twee zinnen in eigen woorden. Geen overgenomen alinea. Hooguit een heel korte quote tussen aanhalingstekens.",
  "url": "https://voorbeeld.nl/artikel/"
}
```

3. Velden:
   - `medium` — mediumnaam (verplicht)
   - `datum` — ISO `YYYY-MM-DD` (verplicht; bepaalt sortering en `<time>`)
   - `kop` — titel zoals je die toont (verplicht)
   - `samenvatting` — korte eigen tekst, 1–2 zinnen (verplicht)
   - `url` — volledige `http(s)`-link (verplicht; opent in nieuw tabblad)
4. Check lokaal: `php artisan test --filter=InDeMedia` (of de hele suite).
5. Commit + PR naar `staging`. Bump `VERSION` / `CHANGELOG.md` zoals bij andere contentreleases.

Ongeldige rijen (lege velden, foute datum, geen http(s)-URL) worden overgeslagen.

## Journalisten

Het blok «Ben je journalist?» op de pagina verwijst naar `config('site.contact_email')`.
Er is (nog) geen aparte persmap in de repo; voeg die niet toe in de view zonder echt bestand.
