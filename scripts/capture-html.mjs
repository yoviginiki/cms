/**
 * Screenshot a local HTML file to a PNG (Builder P1 — Library preview thumbnails).
 * Usage: node scripts/capture-html.mjs <htmlFilePath> [widthxheight]
 * Prints base64 PNG to stdout, or "ERROR: <msg>" to stderr with exit 1.
 *
 * Renders trusted, server-generated HTML (a library item's own block tree wrapped
 * in the site's design tokens) — NOT arbitrary user URLs, so there is no SSRF
 * surface here. Network is allowed only so token @import font CSS can resolve.
 */
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const file = process.argv[2];
const dims = (process.argv[3] || '1200x800').split('x').map((n) => parseInt(n, 10));
const [width, height] = [dims[0] || 1200, dims[1] || 800];

if (!file) {
  process.stderr.write('ERROR: an HTML file path is required\n');
  process.exit(1);
}

let html;
try {
  html = readFileSync(file, 'utf8');
} catch {
  process.stderr.write('ERROR: could not read the HTML file\n');
  process.exit(1);
}

let browser;
try {
  browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({
    viewport: { width, height },
    deviceScaleFactor: 2, // crisp thumbnails on retina
    reducedMotion: 'reduce',
    // a file:// base so any relative asset refs resolve predictably
    baseURL: pathToFileURL(file).href,
  });
  const page = await ctx.newPage();
  await page.setContent(html, { waitUntil: 'networkidle', timeout: 15000 }).catch(async () => {
    await page.setContent(html, { waitUntil: 'domcontentloaded', timeout: 8000 });
  });
  await page.waitForTimeout(600);
  // capture just the top viewport-worth — a thumbnail, not the whole page
  const buf = await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width, height } });
  process.stdout.write(buf.toString('base64'));
  await browser.close();
} catch (e) {
  if (browser) await browser.close().catch(() => {});
  process.stderr.write('ERROR: ' + (e && e.message ? e.message.slice(0, 200) : 'capture failed') + '\n');
  process.exit(1);
}
