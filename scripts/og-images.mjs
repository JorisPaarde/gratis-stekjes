#!/usr/bin/env node
/**
 * Genereert social images (Open Graph, 1200×630) naar public/assets/og/.
 *
 *   npm i --no-save playwright && npx playwright install chromium   (eenmalig)
 *   node scripts/og-images.mjs            # alles
 *   node scripts/og-images.mjs pagina     # alleen vaste pagina's
 *   node scripts/og-images.mjs soort      # alleen stektips per soort
 *   node scripts/og-images.mjs stad       # alleen stadspagina's
 *
 * Bronnen: data/stektips.json, data/soort-afbeeldingen.json, data/steden.json,
 * public/assets/images/home-v2/*.jpg (Pexels / OSM) en public/assets/images/soorten/*.
 * Soortfoto's zijn meestal CC BY-SA: de maker + licentie staan zichtbaar in de afbeelding.
 */
import { readFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'public/assets/og');
const only = process.argv[2] || 'alles';

let chromium;
try {
  ({ chromium } = await import('playwright'));
} catch {
  console.error('Playwright ontbreekt. Draai eerst: npm i --no-save playwright && npx playwright install chromium');
  process.exit(1);
}

const json = (rel) => JSON.parse(readFileSync(join(ROOT, rel), 'utf8'));
const dataUri = (rel) => {
  const abs = join(ROOT, rel);
  const ext = rel.split('.').pop().toLowerCase();
  const mime = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', svg: 'image/svg+xml', woff2: 'font/woff2' }[ext];
  return `data:${mime};base64,${readFileSync(abs).toString('base64')}`;
};
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

const FONTS = `
@font-face { font-family: 'Passion One'; font-weight: 400; src: url(${dataUri('public/fonts/passion-one-latin-400-normal.woff2')}) format('woff2'); }
@font-face { font-family: 'Titillium Web'; font-weight: 400; src: url(${dataUri('public/fonts/titillium-web-latin-400-normal.woff2')}) format('woff2'); }
@font-face { font-family: 'Titillium Web'; font-weight: 700; src: url(${dataUri('public/fonts/titillium-web-latin-700-normal.woff2')}) format('woff2'); }
`;

/** Eén ontwerp: groen tekstvlak links, foto rechts (of alleen groen). */
function kaart({ label, titel, sub, foto, credit }) {
  const lang = titel.length > 38;
  return `<!doctype html><html lang="nl"><head><meta charset="utf-8"><style>
${FONTS}
*{box-sizing:border-box;margin:0}
body{width:1200px;height:630px;overflow:hidden;font-family:'Titillium Web',sans-serif;background:#163820;color:#fff}
.k{display:grid;grid-template-columns:${foto ? '640px 560px' : '1200px'};height:630px}
.t{position:relative;padding:64px 64px 56px;display:flex;flex-direction:column;background:linear-gradient(160deg,#1f4d2b,#163820)}
.merk{font-family:'Passion One';font-size:30px;letter-spacing:.03em;text-transform:uppercase;color:#e6f2e1}
.label{margin-top:46px;align-self:flex-start;background:#e8833a;color:#1a1210;font-weight:700;font-size:24px;padding:6px 18px;border-radius:999px}
h1{font-family:'Passion One';font-weight:400;font-size:${lang ? 66 : 82}px;line-height:1.0;margin-top:22px;max-width:${foto ? 520 : 680}px}
.sub{margin-top:20px;font-size:28px;line-height:1.3;color:#d9e8d4;max-width:${foto ? 520 : 640}px}
.url{margin-top:auto;font-weight:700;font-size:26px;color:#ffe2b8}
.f{position:relative;background:#e6f2e1}
.f img{width:100%;height:100%;object-fit:cover;display:block}
.c{position:absolute;right:0;bottom:0;max-width:100%;background:rgba(0,0,0,.55);color:#fff;font-size:15px;padding:5px 12px;border-top-left-radius:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.blad{position:absolute;right:70px;top:120px;width:380px;height:380px;border-radius:50%;background:rgba(230,242,225,.07);display:grid;place-items:center}
.blad svg{width:230px;height:230px;stroke:#a9c9a0;fill:none;stroke-width:1.2;stroke-linecap:round;stroke-linejoin:round}
</style></head><body><div class="k">
<div class="t">${foto ? '' : '<div class="blad"><svg viewBox="0 0 24 24"><path d="M12 22v-7"/><path d="M9 22h6"/><path d="M12 15c-3.5-1-6-3.5-6-7 4 0 6 2.5 6 7Z"/><path d="M12 15c3.5-1 6-3.5 6-7-4 0-6 2.5-6 7Z"/><path d="M12 10c-1.5-3-.5-5.5 2-7"/></svg></div>'}
<div class="merk">Gratis planten</div>
${label ? `<div class="label">${esc(label)}</div>` : ''}
<h1>${esc(titel)}</h1>
${sub ? `<p class="sub">${esc(sub)}</p>` : ''}
<div class="url">gratisplanten.nl</div>
</div>
${foto ? `<div class="f"><img src="${dataUri(foto)}" alt="">${credit ? `<div class="c">${esc(credit)}</div>` : ''}</div>` : ''}
</div></body></html>`;
}

const jobs = [];

if (only === 'alles' || only === 'pagina') {
  const P = 'public/assets/images/home-v2/';
  jobs.push(
    { file: 'standaard', titel: 'Gratis planten, stekjes en zaden bij jou in de buurt', sub: 'Zelf ophalen in Nederland en België. Meer groen, meer contact.', foto: P + 'delen-960.jpg' },
    { file: 'home', titel: 'Gratis planten, stekjes en zaden bij jou in de buurt', sub: 'Zelf ophalen in Nederland en België. Meer groen, meer contact.', foto: P + 'stekjes-960.jpg' },
    { file: 'aanbod', label: 'Aanbod en kaart', titel: 'Wat is er bij jou in de buurt?', sub: 'Gratis planten, stekjes en zaden van mensen in de buurt en op vaste deelplekken.', foto: P + 'kaart-960.jpg', credit: '© OpenStreetMap-bijdragers' },
    { file: 'stektips', label: 'Stektips', titel: 'Zelf planten stekken, zaaien en snoeien', sub: 'Tips per soort: hoe en wanneer het lukt.', foto: P + 'aanmelden-960.jpg' },
    { file: 'deelplekken', label: 'Deelplekken', titel: 'Plantenasiels, plantenbiebs en zadenbibliotheken', sub: 'Vind een plek in de buurt om planten te brengen, ruilen of op te halen.', foto: P + 'kaart-960.jpg', credit: '© OpenStreetMap-bijdragers' },
    { file: 'hoe-werkt-het', label: 'Hoe werkt het', titel: 'Gratis, zelf ophalen en een praatje', sub: 'Zoek een plant of geef er een weg, met één account.', foto: P + 'delen-960.jpg' },
    { file: 'gratis-planten', label: 'Per stad', titel: 'Gratis planten in jouw stad', sub: 'Aanbod en deelplekken in 46 steden in Nederland en België.' },
  );
}

if (only === 'alles' || only === 'soort') {
  const stektips = json('data/stektips.json').soorten;
  const fotos = json('data/soort-afbeeldingen.json').soorten;
  for (const s of stektips) {
    const f = fotos[s.slug];
    const foto = f && f.bestand && existsSync(join(ROOT, 'public', f.bestand)) ? 'public/' + f.bestand : null;
    const kans = String(s.kans || '').toLowerCase();
    const niveau = kans.startsWith('makkelijk')
      ? 'Makkelijk om zelf te vermeerderen'
      : kans.includes('lastig') || kans.includes('laag')
        ? 'Een uitdaging om zelf te vermeerderen'
        : 'Met wat geduld zelf te vermeerderen';
    jobs.push({
      file: 'soort/' + s.slug,
      label: 'Stektip',
      titel: s.naam,
      sub: `${niveau}. Hoe en wanneer lees je op de site.`,
      foto,
      credit: foto && f.auteur ? `Foto: ${f.auteur} · ${f.licentie || ''}`.trim() : null,
    });
  }
}

if (only === 'alles' || only === 'stad') {
  for (const c of json('data/steden.json').steden) {
    jobs.push({ file: 'stad/' + c.slug, label: c.provincie, titel: `Gratis planten in ${c.naam}`, sub: 'Planten, stekjes en zaden in de buurt. Zelf ophalen, met een praatje.' });
  }
}

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const job of jobs) {
  const out = join(OUT, job.file + '.jpg');
  mkdirSync(dirname(out), { recursive: true });
  await page.setContent(kaart(job), { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: out, type: 'jpeg', quality: 74 });
}
await browser.close();
console.log(`${jobs.length} social images in public/assets/og/`);
