#!/usr/bin/env node
/**
 * Visual migration diff: screenshot each origin/new page pair and score the
 * pixel mismatch, with no native image-diff dependency — the comparison runs
 * on a <canvas> inside the already-required Playwright browser.
 *
 * Usage: node migration-shots.mjs <pairs.json> <outDir>
 *   pairs.json: [{ "label": "page:about", "origin": "https://old/x", "new": "https://new/x" }]
 * Writes <outDir>/<label>-origin.png, <label>-new.png and prints a JSON report
 * to stdout: [{ label, mismatchPct, heightOrigin, heightNew, error? }]
 */
import { chromium } from 'playwright';
import { readFileSync } from 'fs';
import { mkdirSync } from 'fs';
import path from 'path';

const [pairsFile, outDir] = process.argv.slice(2);
if (!pairsFile || !outDir) {
  console.error('usage: migration-shots.mjs <pairs.json> <outDir>');
  process.exit(1);
}
const pairs = JSON.parse(readFileSync(pairsFile, 'utf8'));
mkdirSync(outDir, { recursive: true });

const safe = (label) => label.replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const results = [];

async function capture(url, file) {
  const page = await context.newPage();
  try {
    await page.goto(url, { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1500); // fonts/lazy content settle
    const height = await page.evaluate(() => document.body.scrollHeight);
    await page.screenshot({ path: file, fullPage: true });
    return height;
  } finally {
    await page.close();
  }
}

// Compare two PNGs in a browser canvas: % of pixels whose channel delta > 24,
// over the region both images share. Downscale to width 720 to keep it fast.
async function mismatch(fileA, fileB) {
  const page = await context.newPage();
  try {
    const toDataUrl = (f) => 'data:image/png;base64,' + readFileSync(f).toString('base64');
    return await page.evaluate(async ([a, b]) => {
      const load = (src) => new Promise((res, rej) => {
        const img = new Image();
        img.onload = () => res(img);
        img.onerror = rej;
        img.src = src;
      });
      const [ia, ib] = await Promise.all([load(a), load(b)]);
      const W = 720;
      const ha = Math.round(ia.height * (W / ia.width));
      const hb = Math.round(ib.height * (W / ib.width));
      const H = Math.min(ha, hb);
      const ctx = (h) => {
        const c = document.createElement('canvas');
        c.width = W;
        c.height = h;
        return c.getContext('2d', { willReadFrequently: true });
      };
      const ca = ctx(ha); ca.drawImage(ia, 0, 0, W, ha);
      const cb = ctx(hb); cb.drawImage(ib, 0, 0, W, hb);
      const da = ca.getImageData(0, 0, W, H).data;
      const db = cb.getImageData(0, 0, W, H).data;
      let diff = 0;
      const total = W * H;
      for (let i = 0; i < total * 4; i += 4) {
        if (Math.abs(da[i] - db[i]) > 24 || Math.abs(da[i + 1] - db[i + 1]) > 24 || Math.abs(da[i + 2] - db[i + 2]) > 24) diff++;
      }
      // Uncompared tail (height difference) counts as mismatch.
      const tail = Math.max(ha, hb) - H;
      return Math.round(((diff + tail * W) / (Math.max(ha, hb) * W)) * 1000) / 10;
    }, [toDataUrl(fileA), toDataUrl(fileB)]);
  } finally {
    await page.close();
  }
}

for (const pair of pairs) {
  const base = safe(pair.label);
  const entry = { label: pair.label };
  try {
    entry.heightOrigin = await capture(pair.origin, path.join(outDir, `${base}-origin.png`));
    entry.heightNew = await capture(pair.new, path.join(outDir, `${base}-new.png`));
    entry.mismatchPct = await mismatch(path.join(outDir, `${base}-origin.png`), path.join(outDir, `${base}-new.png`));
    entry.originShot = `${base}-origin.png`;
    entry.newShot = `${base}-new.png`;
  } catch (e) {
    entry.error = String(e.message || e).slice(0, 200);
  }
  results.push(entry);
  console.error(`${pair.label}: ${entry.error ? 'ERROR ' + entry.error : entry.mismatchPct + '% mismatch'}`);
}

await browser.close();
console.log(JSON.stringify(results));
