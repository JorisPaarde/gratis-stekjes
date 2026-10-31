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
    await page.screenshot({
      path: path.join(artifactsDir, 'full-393.png'),
      fullPage: true,
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.screenshot({
      path: path.join(artifactsDir, 'full-1440.png'),
      fullPage: true,
    });
  });
});
