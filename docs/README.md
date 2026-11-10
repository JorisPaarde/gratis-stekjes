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

`/s/{code}` doet een **302** naar `{target_path}?utm_…` (default `/ga/word-lid`)
met dezelfde UTM-parameters als `tracking_url`. Optioneel veld `target_path` bij mint
kiest het landingspad (alleen interne paden). De meting (dashboard op
`utm_content=<post_logs.id>`; `cta_click` op `/ga/word-lid`) blijft daardoor
gekoppeld. De `/s/`-redirect zelf telt **niet** als pageview en niet als
`cta_click`.

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
