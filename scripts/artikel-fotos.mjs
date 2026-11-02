#!/usr/bin/env node
/**
 * Eenmalige generator: _incoming/gp-afbeeldingen/*.jpg → public/assets/images/artikelen/
 * AVIF + WebP + JPG op 480 / 800 / 1200 / 1600. Commit de output; bronmap daarna weg.
 *
 *   npm i --no-save sharp && node scripts/artikel-fotos.mjs
 */
import { mkdirSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SRC = join(ROOT, '_incoming/gp-afbeeldingen');
const OUT = join(ROOT, 'public/assets/images/artikelen');
const WIDTHS = [480, 800, 1200, 1600];

/** Alleen de bestanden die we echt gebruiken (geen alternatieven uit lijst.md). */
const PHOTOS = {
  s01: { file: 's01-stekken.jpg', alt: 'Vensterbank met een rij glazen potjes waarin stekjes wortelen', maker: 'Elly M', artikel: 'stekken', slot: 'hero' },
  s02: { file: 's02-stekken.jpg', alt: 'Kopstek van een kamerplant in een glazen fles met water', maker: 'Ahmad Attari', artikel: 'stekken', slot: 'kaart-kopstek' },
  s03: { file: 's03-stekken.jpg', alt: 'Bladstekken van vetplanten in een kweekbakje met aarde', maker: '8kka ame', artikel: 'stekken', slot: 'kaart-bladstek' },
  s04: { file: 's04-stekken.jpg', alt: 'Jonge stek van rozemarijn in een klein potje met aarde', maker: 'Christine', artikel: 'stekken', slot: 'kaart-halfrijp' },
  s07: { file: 's07-stekken.jpg', alt: 'Handen knippen met een schaartje een stek van een plant, met ontsmettingsmiddel ernaast', maker: 'feey', artikel: 'stekken', slot: 'stappen-kopstek' },
  s08: { file: 's08-stekken.jpg', alt: 'Stekken van gatenplant in glazen potten met water op een aanrecht', maker: 'Brina Blum', artikel: 'stekken', slot: 'vergelijk-water' },
  s09: { file: 's09-stekken.jpg', alt: 'Drie jonge stekjes in terracotta potjes met aarde', maker: 'Christine', artikel: 'stekken', slot: 'vergelijk-grond' },
  s10: { file: 's10-stekken.jpg', alt: 'Witte wortels groeien uit een knoop van een stengel in water', maker: 'feey', artikel: 'stekken', slot: 'wortelen' },
  n01: { file: 'n01-snoeien.jpg', alt: 'Snoeischaar knipt een groene scheut boven een knop', maker: 'Margarita Shtyfura', artikel: 'snoeien', slot: 'hero' },
  n02: { file: 'n02-snoeien.jpg', alt: 'Paarse bloeiaar van de vlinderstruik (buddleja), bloeit op nieuw hout', maker: 'Aditi Panatu', artikel: 'snoeien', slot: 'vergelijk-nieuw-hout' },
  n03: { file: 'n03-snoeien.jpg', alt: 'Gele bloesem van forsythia aan oude takken', maker: 'Yoksel Zok', artikel: 'snoeien', slot: 'vergelijk-oud-hout' },
  n05: { file: 'n05-snoeien.jpg', alt: 'Gesnoeide fruitboom met kale takken in de winter', maker: 'MChe Lee', artikel: 'snoeien', slot: 'snoeikalender' },
  n06: { file: 'n06-snoeien.jpg', alt: 'Snoeischaar op afgeknipte takjes en touw, klaar om te stekken', maker: 'Sofia Holmberg', artikel: 'snoeien', slot: 'snoeisel-naar-stek' },
  v1: { file: 'v1-stekken.jpg', alt: 'Vensterbank met stekjes in vaasjes en potjes', maker: 'Kambiz Daneshjou', artikel: 'stekken', slot: 'video-1ve5eEls-cg', video: '1ve5eEls-cg' },
  v2: { file: 'v2-stekken.jpg', alt: 'Jonge zaailingen en stekjes in een kweekbak', maker: 'Markus Spiske', artikel: 'stekken', slot: 'video-E0KPdbgFaLw', video: 'E0KPdbgFaLw' },
  v3: { file: 'v3-stekken.jpg', alt: 'Kale takken in de winter, geschikt voor winterstekken', maker: 'Michel Meuleman', artikel: 'stekken', slot: 'video-nEM7h1Ywr14', video: 'nEM7h1Ywr14' },
  v4: { file: 'v4-stekken.jpg', alt: 'Plant met zichtbare wortels, uit de pot gehaald', maker: 'Mario Heller', artikel: 'stekken', slot: 'video-eBXdt0yzlew', video: 'eBXdt0yzlew' },
  v5: { file: 'v5-snoeien.jpg', alt: 'Hand met snoeischaar snoeit een groene struik', maker: 'Margarita Shtyfura', artikel: 'snoeien', slot: 'video-DDDNKRT0mvY', video: 'DDDNKRT0mvY' },
  v6: { file: 'v6-snoeien.jpg', alt: 'Pluimhortensia in bloei in de tuin', maker: 'Annie Spratt', artikel: 'snoeien', slot: 'video-SplNsb3qI9U', video: 'SplNsb3qI9U' },
  v7: { file: 'v7-snoeien.jpg', alt: 'Handen met handschoenen snoeien een kale boom in de winter', maker: 'Jakub Żerdzicki', artikel: 'snoeien', slot: 'video-Bd_bxIDexU4', video: 'Bd_bxIDexU4' },
  v8: { file: 'v8-snoeien.jpg', alt: 'Snoeischaar knipt een twijg in het voorjaar', maker: 'Margarita Shtyfura', artikel: 'snoeien', slot: 'video-GvUVTIS1x_s', video: 'GvUVTIS1x_s' },
};

mkdirSync(OUT, { recursive: true });

const manifest = { widths: WIDTHS, bron: 'Unsplash', licentie: 'Unsplash License', licentie_url: 'https://unsplash.com/license', fotos: {} };

for (const [id, meta] of Object.entries(PHOTOS)) {
  const srcPath = join(SRC, meta.file);
  if (!existsSync(srcPath)) {
    console.error('Ontbreekt:', srcPath);
    process.exit(1);
  }
  const base = sharp(srcPath).rotate();
  const { width: ow, height: oh } = await base.metadata();
  const variants = [];
  for (const w of WIDTHS) {
    if (ow && w > ow && w !== WIDTHS[0]) {
      // Skip upsizing behalve de kleinste (altijd aanwezig).
      continue;
    }
    const tw = Math.min(w, ow || w);
    const pipeline = () => sharp(srcPath).rotate().resize({ width: tw, withoutEnlargement: true });

    const avifName = `${id}-${tw}.avif`;
    const webpName = `${id}-${tw}.webp`;
    const jpgName = `${id}-${tw}.jpg`;

    await pipeline().avif({ quality: 55, effort: 4 }).toFile(join(OUT, avifName));
    await pipeline().webp({ quality: 72 }).toFile(join(OUT, webpName));
    await pipeline().jpeg({ quality: 78, mozjpeg: true, progressive: true }).toFile(join(OUT, jpgName));

    variants.push({ w: tw, avif: `assets/images/artikelen/${avifName}`, webp: `assets/images/artikelen/${webpName}`, jpg: `assets/images/artikelen/${jpgName}` });
  }

  const intrinsicW = ow || 1600;
  const intrinsicH = oh || Math.round(intrinsicW * 0.67);
  manifest.fotos[id] = {
    id,
    alt: meta.alt,
    maker: meta.maker,
    artikel: meta.artikel,
    slot: meta.slot,
    video: meta.video ?? null,
    width: intrinsicW,
    height: intrinsicH,
    variants,
    bron: 'Unsplash',
    licentie: 'Unsplash License',
    licentie_url: 'https://unsplash.com/license',
  };
  console.log(`✓ ${id} (${intrinsicW}×${intrinsicH}) → ${variants.length} breedtes`);
}

writeFileSync(join(ROOT, 'resources/data/artikel-fotos.json'), JSON.stringify(manifest, null, 4) + '\n');
console.log(`\nManifest: resources/data/artikel-fotos.json`);
console.log(`Bestanden: ${readdirSync(OUT).length} in ${OUT}`);
