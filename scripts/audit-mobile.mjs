import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const base = process.env.AUDIT_BASE || 'http://127.0.0.1:8000';
const outDir = path.resolve('storage/app/audit-screenshots');
fs.mkdirSync(outDir, { recursive: true });

const viewports = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'tablet', width: 768, height: 1024 },
];

const pages = [
  { id: 'home', path: '/' },
  { id: 'aanbod', path: '/aanbod' },
  { id: 'stektips', path: '/stektips' },
  { id: 'stektips-show', path: '/stektips/herfstolijfwilg' },
  { id: 'deelplekken', path: '/deelplekken' },
  { id: 'gratis-planten', path: '/gratis-planten' },
  { id: 'gratis-planten-stad', path: '/gratis-planten/amsterdam' },
  { id: 'initiatieven', path: '/initiatieven' },
  { id: 'voor-organisaties', path: '/voor-organisaties' },
  { id: 'organisaties', path: '/organisaties' },
  { id: 'organisatie-show', path: '/organisaties/plantenasiel-haarlem' },
  { id: 'account-login', path: '/account/inloggen' },
  { id: 'account', path: '/account', auth: true },
  { id: 'account-aanbod', path: '/account/aanbod', auth: true },
  { id: 'account-inbox', path: '/account/inbox', auth: true },
  { id: 'beheer', path: '/beheer/overzicht', admin: true },
];

const findings = [];

async function login(page) {
  await page.goto(base + '/account/inloggen', { waitUntil: 'networkidle' });
  await page.fill('form.form input[name="email"]', 'demo1@example.com');
  await page.fill('form.form input[name="password"]', 'password');
  const started = page.locator('form.form input[name="form_started"]');
  if (await started.count()) {
    await started.evaluate((el) => { el.value = String(Math.floor(Date.now() / 1000) - 10); });
  }
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.locator('form.form button[type="submit"]').click(),
  ]);
}

async function adminLogin(page) {
  await page.goto(base + '/beheer', { waitUntil: 'networkidle' });
  await page.fill('main form input[name="password"], form.form input[name="password"]', 'audit-admin-test');
  const started = page.locator('main form input[name="form_started"], form.form input[name="form_started"]');
  if (await started.count()) {
    await started.first().evaluate((el) => { el.value = String(Math.floor(Date.now() / 1000) - 10); });
  }
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.locator('main form button[type="submit"], form.form button[type="submit"]').first().click(),
  ]);
}

async function measure(page, label) {
  const metrics = await page.evaluate(() => {
    const doc = document.documentElement;
    const scrollWidth = Math.max(doc.scrollWidth, document.body.scrollWidth);
    const clientWidth = doc.clientWidth;
    const horizontalOverflow = scrollWidth > clientWidth + 1;
    const smallButtons = [...document.querySelectorAll('a.btn, button.btn, .nav-toggle, .nav-cta, button[type="submit"]')]
      .map((el) => {
        const r = el.getBoundingClientRect();
        return { text: (el.innerText || '').trim().slice(0, 40), w: Math.round(r.width), h: Math.round(r.height) };
      })
      .filter((b) => b.h > 0 && b.h < 44);
    const tables = [...document.querySelectorAll('table')].map((t) => {
      const r = t.getBoundingClientRect();
      const parent = t.parentElement;
      const parentOverflow = parent ? getComputedStyle(parent).overflowX : '';
      return {
        w: Math.round(r.width),
        clipped: r.width > clientWidth + 1 && parentOverflow !== 'auto' && parentOverflow !== 'scroll',
      };
    });
    const maps = [...document.querySelectorAll('#kaart, #deelplekken-kaart, #stad-kaart')].map((el) => {
      const r = el.getBoundingClientRect();
      return { id: el.id, w: Math.round(r.width), h: Math.round(r.height), readable: r.width >= 280 && r.height >= 200 };
    });
    return { scrollWidth, clientWidth, horizontalOverflow, smallButtons, tables, maps };
  });
  findings.push({ label, ...metrics });
  return metrics;
}

const browser = await chromium.launch();
for (const vp of viewports) {
  const context = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
    locale: 'nl-NL',
  });
  const page = await context.newPage();
  let loggedIn = false;
  let adminOk = false;

  for (const p of pages) {
    if (p.auth && !loggedIn) {
      await login(page);
      loggedIn = true;
    }
    if (p.admin && !adminOk) {
      await adminLogin(page);
      adminOk = true;
    }
    const url = base + p.path;
    const resp = await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    const status = resp ? resp.status() : 0;
    await page.waitForTimeout(400);
    const file = `${vp.name}-${p.id}.png`;
    await page.screenshot({ path: path.join(outDir, file), fullPage: true });
    const m = await measure(page, `${vp.name}:${p.id}`);
    console.log(JSON.stringify({ viewport: vp.name, id: p.id, status, file, overflow: m.horizontalOverflow, smallBtns: m.smallButtons.length, maps: m.maps }));
  }
  await context.close();
}
await browser.close();
fs.writeFileSync(path.join(outDir, 'findings.json'), JSON.stringify(findings, null, 2));
console.log('Wrote', outDir);
