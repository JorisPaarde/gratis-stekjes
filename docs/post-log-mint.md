# Post-log mint API

Plantjes en Grok Bot minten via **hetzelfde** endpoint een unieke tracking-URL per FB/IG-post.
Beheer (`/beheer/posts`) blijft read-first: overzicht + conversie; handmatig toevoegen is fallback.

## Env

| Key | Rol |
|-----|-----|
| `POST_LOG_MINT_TOKEN` | Shared secret. Header `X-Post-Log-Token`. Leeg = mint weigert (403). Nooit committen. |

Zet het token op de server (staging/prod) na deploy; niet in git of PR-tekst.

## Endpoint

`POST /api/internal/post-logs`  
Auth: `X-Post-Log-Token: <POST_LOG_MINT_TOKEN>`  
Throttle: 60/min. Response: JSON.

### Voorbeeld (staging, nep-token)

```bash
curl -sS -X POST 'https://staging.gratisplanten.nl/api/internal/post-logs' \
  -H 'Content-Type: application/json' \
  -H 'X-Post-Log-Token: replace-me' \
  -d '{
    "titel": "Oproep stekjes delen in Amsterdam",
    "groep": "Plantenruil Amsterdam",
    "soort": "aankondiging",
    "utm_source": "facebook",
    "target_path": "/tuinverenigingen",
    "link": "https://www.facebook.com/groups/example/posts/123",
    "notitie": "plantjes-run"
  }'
```

Body:

| Veld | Verplicht | Opmerking |
|------|-----------|-----------|
| `titel` | ja | max 200 |
| `groep` | ja | max 120; `groep_slug` via `PostLog::slugForGroep` |
| `soort` | ja | `aankondiging` / `uitleg` / `oproep` / `resultaat` / `anders` |
| `utm_source` | nee | default `facebook`; korte string (facebook/instagram/…) |
| `target_path` | nee | intern pad na tracking; default `/ga/word-lid`. Moet met `/` beginnen; geen `//`, schema of host (anders **422**) |
| `notitie` | nee | max 500 |
| `posted_on` | nee | datum; default vandaag |
| `link` | nee | URL van de sociale post zelf |

Succes **201**:

```json
{
  "ok": true,
  "id": 42,
  "titel": "Oproep stekjes delen in Amsterdam",
  "tracking_url": "https://staging.gratisplanten.nl/tuinverenigingen?utm_source=facebook&utm_medium=social&utm_campaign=plantenruil-amsterdam&utm_content=42",
  "short_url": "https://staging.gratisplanten.nl/s/H7K2M",
  "short_code": "H7K2M",
  "target_path": "/tuinverenigingen",
  "utm": {
    "source": "facebook",
    "medium": "social",
    "campaign": "plantenruil-amsterdam",
    "content": "42"
  },
  "groep": "Plantenruil Amsterdam",
  "soort": "aankondiging",
  "posted_on": "2026-11-06"
}
```

Zonder `target_path` (of leeg): zelfde response met `target_path` `/ga/word-lid` en `tracking_url` naar `/ga/word-lid?utm_…`.

Fouten: **403** (lege/verkeerde token), **422** (validatie, o.a. open-redirect-pogingen op `target_path`).

## Korte deellinks (`short_url`)

Bots moeten **`short_url`** in de Facebook-/Instagram-post zetten (niet de lange
`tracking_url`). Lange UTM-URL’s ogen rommelig in social posts; `/s/{code}` is kort
en stabiel.

| Onderdeel | Gedrag |
|-----------|--------|
| Code | 5 tekens uit `23456789ABCDEFGHJKMNPQRSTUVWXYZ` (geen 0/O/1/I/L) |
| Route | `GET /s/{code}` → **302** naar `{target_path}?utm_source=…&utm_medium=social&utm_campaign=…&utm_content=<id>` (default `target_path` = `/ga/word-lid`) |
| Meting | Identiek aan `tracking_url`: UTM blijft behouden; op `/ga/word-lid` ook `cta_click`; dashboard op `utm_content=<post_logs.id>` |
| Dubbeltelling | De `/s/`-redirect telt **niet** als pageview (alleen 200-HTML) en niet als `cta_click` |
| SEO | Response-header `X-Robots-Tag: noindex, nofollow`; **niet** in sitemap; **geen** `Disallow: /s/` in robots.txt (Facebook’s crawler volgt de redirect en leest OG-tags van de doelpagina) |
| Open redirect | Alleen intern relatief pad (`target_path` gevalideerd bij mint); nooit een externe Location |
| Onbekende code | Vriendelijke 404-pagina |

Bestaande clients die alleen `tracking_url` lezen blijven werken (achterwaarts compatibel; default blijft `/ga/word-lid`).
Nieuwe bots: gebruik `short_url`; optioneel `target_path` voor landingspagina’s zoals `/tuinverenigingen` of `/stektips/kiwibes`.

`/beheer/posts` toont de korte link (kopieerbaar), de lange tracking-URL en het doelpad.

## Tracking-flow

1. Bezoeker opent `short_url` (`/s/{code}`) of direct `tracking_url`.
2. Korte link: 302 → `{target_path}?utm_…` (geen meting op `/s/`; default `/ga/word-lid`).
3. Op `/ga/word-lid`: meet `cta_click` en stuurt door naar `/account/registreren` (UTM behouden). Op andere doelpaden: UTM/first-touch blijft in de conversieflow.
4. Dashboard-stats aggregatie blijft op `utm_content=<post_logs.id>`.
