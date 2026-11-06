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
| `notitie` | nee | max 500 |
| `posted_on` | nee | datum; default vandaag |
| `link` | nee | URL van de sociale post zelf |

Succes **201**:

```json
{
  "ok": true,
  "id": 42,
  "titel": "Oproep stekjes delen in Amsterdam",
  "tracking_url": "https://staging.gratisplanten.nl/ga/word-lid?utm_source=facebook&utm_medium=social&utm_campaign=plantenruil-amsterdam&utm_content=42",
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

Fouten: **403** (lege/verkeerde token), **422** (validatie).

Tracking-URL’s landen op `/ga/word-lid` (behoudt UTM; meet `cta_click`). Dashboard-stats blijven op `utm_content=<post_logs.id>`.
