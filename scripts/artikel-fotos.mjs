#!/usr/bin/env node
/**
 * Eenmalige generator: _incoming/gp-afbeeldingen/*.jpg → public/assets/images/artikelen/
 * AVIF + WebP + JPG op 480 / 800 / 1200 / 1600 (kaartfoto's: 480 / 800). Commit de output; bronmap daarna weg.
 * Ontbreekt een bronbestand maar staat de foto al in het manifest, dan blijft die ongewijzigd staan
 * (alleen meta wordt bijgewerkt), zodat je losse foto's kunt toevoegen zonder alle bronnen.
 *
 * Kaartfoto's (`kaart_voor`): de foto op het kaartje van dat artikel in «Meer over dit onderwerp».
 * Elk artikel dat als kaartje verschijnt, heeft precies één eigen kaartfoto (afgedwongen in tests).
 *
 *   npm i --no-save sharp && node scripts/artikel-fotos.mjs
 */
import { mkdirSync, readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SRC = join(ROOT, '_incoming/gp-afbeeldingen');
const OUT = join(ROOT, 'public/assets/images/artikelen');
const WIDTHS = [480, 800, 1200, 1600];
const KAART_WIDTHS = [480, 800];
const MANIFEST = join(ROOT, 'resources/data/artikel-fotos.json');

/** Alleen de bestanden die we echt gebruiken (geen alternatieven uit lijst.md). */
const PHOTOS = {
  s01: { file: 's01-stekken.jpg', alt: 'Vensterbank met een rij glazen potjes waarin stekjes wortelen', maker: 'Elly M', artikel: 'stekken', slot: 'hero', kaart_voor: 'stekken' },
  s02: { file: 's02-stekken.jpg', alt: 'Kopstek van een kamerplant in een glazen fles met water', maker: 'Ahmad Attari', artikel: 'stekken', slot: 'kaart-kopstek' },
  s03: { file: 's03-stekken.jpg', alt: 'Bladstekken van vetplanten in een kweekbakje met aarde', maker: '8kka ame', artikel: 'stekken', slot: 'kaart-bladstek' },
  s04: { file: 's04-stekken.jpg', alt: 'Jonge stek van rozemarijn in een klein potje met aarde', maker: 'Christine', artikel: 'stekken', slot: 'kaart-halfrijp' },
  s07: { file: 's07-stekken.jpg', alt: 'Handen knippen met een schaartje een stek van een plant, met ontsmettingsmiddel ernaast', maker: 'feey', artikel: 'stekken', slot: 'stappen-kopstek' },
  s08: { file: 's08-stekken.jpg', alt: 'Stekken van gatenplant in glazen potten met water op een aanrecht', maker: 'Brina Blum', artikel: 'stekken', slot: 'vergelijk-water' },
  s09: { file: 's09-stekken.jpg', alt: 'Drie jonge stekjes in terracotta potjes met aarde', maker: 'Christine', artikel: 'stekken', slot: 'vergelijk-grond' },
  s10: { file: 's10-stekken.jpg', alt: 'Witte wortels groeien uit een knoop van een stengel in water', maker: 'feey', artikel: 'stekken', slot: 'wortelen' },
  n01: { file: 'n01-snoeien.jpg', alt: 'Snoeischaar knipt een groene scheut boven een knop', maker: 'Margarita Shtyfura', artikel: 'snoeien', slot: 'hero', kaart_voor: 'snoeien' },
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
  // Kaartfoto's voor «Meer over dit onderwerp» (1600×1000, 16:10).
  'k-vlinderstruik-snoeien': { file: 'k-vlinderstruik-snoeien.jpg', alt: 'Paarse bloeiaren van de vlinderstruik met twee vlinders', maker: 'Henrik Hageland', kaart_voor: 'vlinderstruik-snoeien', bron_url: 'https://unsplash.com/photos/ZMr2MWyOA50' },
  'k-olijfboom-snoeien': { file: 'k-olijfboom-snoeien.jpg', alt: 'Groene olijven aan een tak van een olijfboom tegen een blauwe lucht', maker: 'Susana Bartolome', kaart_voor: 'olijfboom-snoeien', bron_url: 'https://unsplash.com/photos/-ZJ9fM0kgZM' },
  'k-rozen-snoeien': { file: 'k-rozen-snoeien.jpg', alt: 'Rozenstruik vol roze bloemen', maker: 'Alina Chernovolova', kaart_voor: 'rozen-snoeien', bron_url: 'https://unsplash.com/photos/aWe-ycdo4MI' },
  'k-vijg-snoeien': { file: 'k-vijg-snoeien.jpg', alt: 'Jonge vijgen tussen de grote handvormige bladeren van een vijgenboom', maker: 'Delia Giandeini', kaart_voor: 'vijg-snoeien', bron_url: 'https://unsplash.com/photos/mSnqTA_bPfo' },
  'k-hortensia-snoeien': { file: 'k-hortensia-snoeien.jpg', alt: 'Roze-paarse bloemschermen van een hortensia', maker: 'Summer Quinn', kaart_voor: 'hortensia-snoeien', bron_url: 'https://unsplash.com/photos/a_AaJcNeksY' },
  'k-lavendel-snoeien': { file: 'k-lavendel-snoeien.jpg', alt: 'Bloeiende lavendel in een rij', maker: 'Mario Mendez', kaart_voor: 'lavendel-snoeien', bron_url: 'https://unsplash.com/photos/kkhww8996mg' },
  'k-snoeiafval-als-stek': { file: 'k-snoeiafval-als-stek.jpg', alt: 'Afgeknipte bladrijke takjes en een schaar, klaar om er stekken van te maken', maker: 'C Cai', kaart_voor: 'snoeiafval-als-stek', bron_url: 'https://unsplash.com/photos/NI4yT-s8Bbc' },
  'k-kopstek-en-scheutstek': { file: 'k-kopstek-en-scheutstek.jpg', alt: 'Zachte scheuten met blad naast een snoeischaar', maker: 'Irena Carpaccio', kaart_voor: 'kopstek-en-scheutstek', bron_url: 'https://unsplash.com/photos/wtB6hAnTf2E' },
  'k-bladstek': { file: 'k-bladstek.jpg', alt: 'Losse vetplantblaadjes met nieuwe worteltjes en rozetjes', maker: 'Corinne Kutz', kaart_voor: 'bladstek', bron_url: 'https://unsplash.com/photos/7ZtPwmSKj6U' },
  'k-stekken-in-water-of-grond': { file: 'k-stekken-in-water-of-grond.jpg', alt: 'Stekken van een klimop-plant (pothos) in glazen flesjes met water', maker: 'Mahdi Dastmard', kaart_voor: 'stekken-in-water-of-grond', bron_url: 'https://unsplash.com/photos/7AQopxp_Bmw' },
  'k-wanneer-stekken': { file: 'k-wanneer-stekken.jpg', alt: 'Rozemarijn vol jonge scheuten', maker: 'Ian Yates', kaart_voor: 'wanneer-stekken', bron_url: 'https://unsplash.com/photos/C4qF96OqM4w' },
  'k-stekgrond-zelf-maken': { file: 'k-stekgrond-zelf-maken.jpg', alt: 'Schepje met stekgrond naast een hoop potgrond', maker: 'Neslihan Gunaydin', kaart_voor: 'stekgrond-zelf-maken', bron_url: 'https://unsplash.com/photos/BduDcrySLKM' },
  'k-winterhoutstek': { file: 'k-winterhoutstek.jpg', alt: 'Kale tak met knoppen in de winter', maker: 'Rose Bahmani', kaart_voor: 'winterhoutstek', bron_url: 'https://unsplash.com/photos/sMm472qcKMo' },
  'k-wilgenwater': { file: 'k-wilgenwater.jpg', alt: 'Takken van de katwilg met zachte katjes in een vaas', maker: 'Kelly Sikkema', kaart_voor: 'wilgenwater', bron_url: 'https://unsplash.com/photos/ShxKkUmMgF4' },
  'k-stekpoeder': { file: 'k-stekpoeder.jpg', alt: 'Jonge stek die wortelt in de aarde', maker: 'Steve A Johnson', kaart_voor: 'stekpoeder', bron_url: 'https://unsplash.com/photos/6e37HQnM5m4' },
  'k-scheuren': { file: 'k-scheuren.jpg', alt: 'Volle pol hosta met lichtgroene bladeren, klaar om te scheuren', maker: 'JOGphotos', kaart_voor: 'scheuren', bron_url: 'https://unsplash.com/photos/A-wGp0G1_y0' },
  'k-scheuren-en-afleggen': { file: 'k-scheuren-en-afleggen.jpg', alt: 'Aardbeiplant met rijpe en onrijpe aardbeien en bloemetjes', maker: 'Oliver Hale', kaart_voor: 'scheuren-en-afleggen', bron_url: 'https://unsplash.com/photos/Qse3AFWnc48' },
  'k-fruitbomen-enten': { file: 'k-fruitbomen-enten.jpg', alt: 'Rode appels aan een tak van een appelboom', maker: 'Kelsey Todd', kaart_voor: 'fruitbomen-enten', bron_url: 'https://unsplash.com/photos/ENcI37ufNSM' },
  'k-onderstammen-per-fruitboom': { file: 'k-onderstammen-per-fruitboom.jpg', alt: 'Rijen jonge appelboompjes aan palen in een boomgaard', maker: 'Kerani', kaart_voor: 'onderstammen-per-fruitboom', bron_url: 'https://unsplash.com/photos/3rmZ7Uv4Ah0' },
  'k-marcotteren': { file: 'k-marcotteren.jpg', alt: 'Rode bloemtros van een rododendron', maker: 'Annie Spratt', kaart_voor: 'marcotteren', bron_url: 'https://unsplash.com/photos/hFJbLRUhFrQ' },
  'k-fruitboom-uit-pit-of-zaad': { file: 'k-fruitboom-uit-pit-of-zaad.jpg', alt: 'Doorgesneden appel met de pitten zichtbaar', maker: 'Tracey Parish', kaart_voor: 'fruitboom-uit-pit-of-zaad', bron_url: 'https://unsplash.com/photos/0Cnb550POeY' },
  'k-natuurlijk-bewortelen': { file: 'k-natuurlijk-bewortelen.jpg', alt: 'Dichte witte wortels van een stek in een glas water', maker: 'Rafayel Yeranosyan', kaart_voor: 'natuurlijk-bewortelen', bron_url: 'https://unsplash.com/photos/0mgjWL4OIrY' },
  'k-afleggen': { file: 'k-afleggen.jpg', alt: 'Gele katjes aan een tak van de hazelaar', maker: 'Jevgeņijs Grigorjevs', kaart_voor: 'afleggen', bron_url: 'https://unsplash.com/photos/z_nOAvaWtKs' },
  'k-uitlopers': { file: 'k-uitlopers.jpg', alt: 'Graslelie in een terracotta pot', maker: 'Naoki Suzuki', kaart_voor: 'uitlopers', bron_url: 'https://unsplash.com/photos/Iu1gkau3084' },
  'k-zaaien': { file: 'k-zaaien.jpg', alt: 'Jonge zaailingen die net boven de aarde uitkomen', maker: 'onehundredseventyfive', kaart_voor: 'zaaien', bron_url: 'https://unsplash.com/photos/Zg0m32COaZU' },
  'k-kruiden-vermeerderen': { file: 'k-kruiden-vermeerderen.jpg', alt: 'Rij jonge kruiden in terracotta potjes', maker: 'Markus Spiske', kaart_voor: 'kruiden-vermeerderen', bron_url: 'https://unsplash.com/photos/IRrUkB8Fq-0' },
  'k-permacultuur-gilde-voedselbos': { file: 'k-permacultuur-gilde-voedselbos.jpg', alt: 'Kippen scharrelen tussen fruitbomen met afgevallen appels', maker: 'Skylar Zilka', kaart_voor: 'permacultuur-gilde-voedselbos', bron_url: 'https://unsplash.com/photos/U46bGX6KRfU' },
};

mkdirSync(OUT, { recursive: true });

const vorige = existsSync(MANIFEST) ? JSON.parse(readFileSync(MANIFEST, 'utf8')) : { fotos: {} };
const manifest = { widths: WIDTHS, bron: 'Unsplash', licentie: 'Unsplash License', licentie_url: 'https://unsplash.com/license', fotos: {} };

for (const [id, meta] of Object.entries(PHOTOS)) {
  const srcPath = join(SRC, meta.file);
  const meta2 = {
    alt: meta.alt,
    maker: meta.maker,
    artikel: meta.artikel ?? null,
    slot: meta.slot ?? (meta.kaart_voor ? 'kaart' : null),
    video: meta.video ?? null,
    kaart_voor: meta.kaart_voor ?? null,
    bron_url: meta.bron_url ?? null,
  };
  if (!existsSync(srcPath)) {
    if (vorige.fotos?.[id]) {
      manifest.fotos[id] = { ...vorige.fotos[id], ...meta2, id };
      console.log(`= ${id} (bron ontbreekt, bestaande varianten behouden)`);
      continue;
    }
    console.error('Ontbreekt:', srcPath);
    process.exit(1);
  }
  const widths = meta.kaart_voor && !meta.artikel ? KAART_WIDTHS : WIDTHS;
  const base = sharp(srcPath).rotate();
  const { width: ow, height: oh } = await base.metadata();
  const variants = [];
  for (const w of widths) {
    if (ow && w > ow && w !== widths[0]) {
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
    ...meta2,
    width: intrinsicW,
    height: intrinsicH,
    variants,
    bron: 'Unsplash',
    licentie: 'Unsplash License',
    licentie_url: 'https://unsplash.com/license',
  };
  console.log(`✓ ${id} (${intrinsicW}×${intrinsicH}) → ${variants.length} breedtes`);
}

writeFileSync(MANIFEST, JSON.stringify(manifest, null, 4) + '\n');
console.log(`\nManifest: resources/data/artikel-fotos.json`);
console.log(`Bestanden: ${readdirSync(OUT).length} in ${OUT}`);
