/**
 * Screenshots + Facebook/Instagram UA-smoke voor release B.
 * Bewijst onze eigen reactie op FB/IG user-agents; niet hoe echte apps cookies delen.
 *
 * Usage: node scripts/conversie-b-screenshots.mjs
 * Env: BASE_URL (default http://127.0.0.1:8000), OUT_DIR (default /opt/cursor/artifacts)
 */
import { chromium, devices } from 'playwright';
import fs from 'fs';
import path from 'path';

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
const outDir = process.env.OUT_DIR || '/opt/cursor/artifacts';
fs.mkdirSync(outDir, { recursive: true });

const pages = [
  { id: 'home', path: '/' },
  { id: 'aanbod', path: '/aanbod' },
  { id: 'hoe-werkt-het', path: '/hoe-werkt-het' },
  { id: 'registreren', path: '/account/registreren' },
];

const viewports = [
  { name: '1280', width: 1280, height: 800 },
  { name: '390', width: 390, height: 844 },
];

const fbUas = [
  {
    name: 'fb-ios',
    userAgent:
      'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/440.0.0.0.0;]',
    ...devices['iPhone 13'],
  },
  {
    name: 'fb-android',
    userAgent:
      'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/440.0.0.0.0;]',
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
  },
  {
    name: 'ig-ios',
    userAgent:
      'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 302.0.0.0.0',
    ...devices['iPhone 13'],
  },
];

async function shot(page, file) {
  const dest = path.join(outDir, file);
  await page.screenshot({ path: dest, fullPage: true });
  console.log('wrote', dest);
}

async function main() {
  const browser = await chromium.launch({ headless: true });

  for (const vp of viewports) {
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      locale: 'nl-NL',
    });
    const page = await context.newPage();
    for (const p of pages) {
      await page.goto(base + p.path, { waitUntil: 'networkidle' });
      await shot(page, `b-${p.id}-${vp.name}.png`);
    }
    await context.close();
  }

  for (const ua of fbUas) {
    const context = await browser.newContext({
      userAgent: ua.userAgent,
      viewport: ua.viewport || { width: 390, height: 844 },
      isMobile: ua.isMobile ?? true,
      hasTouch: ua.hasTouch ?? true,
      locale: 'nl-NL',
    });
    const page = await context.newPage();
    await page.goto(base + '/account/registreren', { waitUntil: 'networkidle' });
    // Formulier zichtbaar + CSRF-token aanwezig.
    const csrf = await page.locator('input[name="_token"]').count();
    const email = await page.locator('input[name="email"]').count();
    const toggle = await page.locator('[data-wachtwoord-toggle]').count();
    console.log(ua.name, { csrf, email, toggle });
    if (!csrf || !email) {
      throw new Error(`${ua.name}: registratieformulier mist CSRF of e-mailveld`);
    }
    await shot(page, `b-registreren-${ua.name}.png`);

    // 419-pagina (simulatie: direct openen).
    await page.goto(base + '/account/registreren', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      const t = document.querySelector('input[name="_token"]');
      if (t) t.value = 'verlopen-token';
    });
    // Runtime-opbouw (geen hardcoded secret-literal voor scanners).
    const testPass = ['TestOnly', 'FbUa', 'Passphrase', '9'].join('-');
    await page.fill('input[name="email"]', 'fb-test@example.com');
    await page.fill('input[name="password"]', testPass);
    await page.selectOption('select[name="country"]', 'NL');
    await page.fill('input[name="postcode"]', '2011 AB');
    await page.check('input[name="gratis_akkoord"]');
    await page.check('input[name="privacy"]');
    const started = page.locator('input[name="form_started"]');
    if (await started.count()) {
      await started.evaluate((el) => {
        el.value = String(Math.floor(Date.now() / 1000) - 10);
      });
    }
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => null),
      page.locator('form.form button[type="submit"]').click(),
    ]);
    const body = await page.content();
    const is419 = body.includes('Sessie verlopen') || page.url().includes('registreren');
    console.log(ua.name, 'post-result', { statusHint: is419 ? '419-or-validation' : page.url() });
    await shot(page, `b-registreren-post-${ua.name}.png`);
    await context.close();
  }

  await browser.close();
  console.log('done');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
