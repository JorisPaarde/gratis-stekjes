import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const artifactsDir = process.env.HOMEPAGE_ARTIFACTS_DIR
  || path.resolve(process.cwd(), 'artifacts/homepage-v2');

fs.mkdirSync(artifactsDir, { recursive: true });

const viewports = [
  { name: '393x852', width: 393, height: 852 },
  { name: '768x1024', width: 768, height: 1024 },
  { name: '1280x720', width: 1280, height: 720 },
  { name: '1512x982', width: 1512, height: 982 },
  { name: '1920x1080', width: 1920, height: 1080 },
] as const;

/** Scroll the page so lazy images load, then wait until every img is complete. */
async function waitForHomeImages(page: Page) {
  await page.evaluate(async () => {
    const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
    const imgs = Array.from(document.querySelectorAll<HTMLImageElement>('.page-home-v2 img'));
    for (const img of imgs) {
      img.scrollIntoView({ block: 'center', inline: 'nearest' });
      await sleep(40);
    }
    // One more full pass top→bottom for IntersectionObserver-based lazy loaders.
    const height = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight,
    );
    for (let y = 0; y < height; y += Math.max(200, window.innerHeight * 0.75)) {
      window.scrollTo(0, y);
      await sleep(30);
    }
    window.scrollTo(0, height);
    await sleep(80);
    window.scrollTo(0, 0);

    await Promise.all(
      imgs.map((img) => {
        if (img.complete && img.naturalWidth > 0) {
          return Promise.resolve();
        }
        return new Promise<void>((resolve) => {
          const done = () => resolve();
          img.addEventListener('load', done, { once: true });
          img.addEventListener('error', done, { once: true });
          // Decode already-cached images that fired before listeners attached.
          if (typeof img.decode === 'function') {
            img.decode().then(done).catch(done);
          }
        });
      }),
    );
  });
}

async function assertHeroAboveFold(page: Page) {
  await expect(page.locator('body.page-home-v2')).toBeVisible();

  const heading = page.locator('#hero-titel');
  const tagline = page.locator('.home-hero-tagline');
  const primary = page.locator('[data-hero-primary]');
  const secondary = page.locator('[data-hero-secondary]');

  await expect(heading).toBeVisible();
  await expect(tagline).toBeVisible();
  await expect(primary).toBeVisible();
  await expect(secondary).toBeVisible();
  await expect(primary).toContainText('Maak gratis een account');

  // Ghost overlay text from the map link must not leak into the hero viewport.
  await expect(page.locator('.home-kaart-label')).toHaveCount(0);
  await expect(page.locator('[data-home-hero]')).not.toContainText(
    'Naar de kaart met aanbod en deelplekken',
  );

  const viewportHeight = page.viewportSize()?.height ?? 0;
  for (const loc of [heading, tagline, primary]) {
    const box = await loc.boundingBox();
    expect(box, 'element should have a bounding box').not.toBeNull();
    if (!box) {
      continue;
    }
    expect(box.y, 'top edge above fold').toBeGreaterThanOrEqual(0);
    expect(box.y + box.height, 'bottom edge above fold').toBeLessThanOrEqual(viewportHeight + 1);
  }

  // Secondary CTA must also stay above the fold on these viewports.
  const secondaryBox = await secondary.boundingBox();
  expect(secondaryBox).not.toBeNull();
  if (secondaryBox) {
    expect(secondaryBox.y + secondaryBox.height).toBeLessThanOrEqual(viewportHeight + 1);
  }
}

test.describe('Homepage v2 hero above the fold', () => {
  for (const vp of viewports) {
    test(`hero fully visible at ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/', { waitUntil: 'networkidle' });
      await assertHeroAboveFold(page);
      await page.screenshot({
        path: path.join(artifactsDir, `hero-${vp.name}.png`),
        fullPage: false,
      });
    });
  }

  test('full page screenshots 393 and 1440', async ({ page }) => {
    await page.setViewportSize({ width: 393, height: 852 });
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page.locator('body.page-home-v2')).toBeVisible();
    await waitForHomeImages(page);
    await page.screenshot({
      path: path.join(artifactsDir, 'full-393.png'),
      fullPage: true,
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/', { waitUntil: 'networkidle' });
    await waitForHomeImages(page);
    await page.screenshot({
      path: path.join(artifactsDir, 'full-1440.png'),
      fullPage: true,
    });
  });
});
