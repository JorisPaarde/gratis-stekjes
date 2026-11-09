# Documentatie — Gratis planten

Korte index van operationele en productdocs. Voor agents: zie ook [`AGENTS.md`](../AGENTS.md).

## Bots: korte deellinks voor social posts

Plantjes en Grok Bot minten per Facebook/Instagram-post een tracking-URL via
`POST /api/internal/post-logs` (header `X-Post-Log-Token`). De response bevat:

| Veld | Gebruik |
|------|---------|
| `short_url` | **Plaats deze in de FB/IG-post** — kort (`/s/{code}`), leesbaar |
| `tracking_url` | Lange UTM-URL (achterwaarts compatibel; zelfde meting) |
| `utm` | source/medium/campaign/content voor logging |

`/s/{code}` doet een **302** naar `/ga/word-lid?utm_…` met dezelfde UTM-parameters
als `tracking_url`. De meting (`cta_click`, dashboard op `utm_content=<post_logs.id>`)
blijft daardoor identiek. De redirect zelf telt **niet** als pageview en niet als
`cta_click` — die valt pas op `/ga/word-lid`.

Volledige API-contract, env en voorbeelden: [`post-log-mint.md`](post-log-mint.md).

## Overige docs

| Bestand | Onderwerp |
|---------|-----------|
| [`INSTALL.md`](INSTALL.md) | Lokaal / server installeren |
| [`ROLLBACK.md`](ROLLBACK.md) | Rollback-stappen |
| [`backups.md`](backups.md) | Versleutelde DB-backups |
| [`avg-inventaris.md`](avg-inventaris.md) | AVG-inventaris |
| [`uitnodigingen.md`](uitnodigingen.md) | Organisatie-uitnodigingen |
| [`in-de-media.md`](in-de-media.md) | Mediavermeldingen (`/in-de-media`) |
| [`design-stijl.md`](design-stijl.md) | Visuele richtlijnen |
