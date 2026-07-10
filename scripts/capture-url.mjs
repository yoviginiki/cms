/**
 * Capture a reference URL to a PNG for the Theme Wizard's vision analysis.
 * Usage: node scripts/capture-url.mjs <url> [widthxheight]
 * Prints base64 PNG to stdout, or "ERROR: <msg>" to stderr with exit 1.
 *
 * SSRF is guarded by the PHP caller (ReferenceCaptureService) BEFORE this runs;
 * this script additionally refuses non-http(s) and caps the wait.
 */
import { chromium } from 'playwright';

const url = process.argv[2];
const dims = (process.argv[3] || '1280x900').split('x').map((n) => parseInt(n, 10));
const [width, height] = [dims[0] || 1280, dims[1] || 900];

if (!url || !/^https?:\/\//i.test(url)) {
  process.stderr.write('ERROR: a http(s) URL is required\n');
  process.exit(1);
}

let browser;
try {
  browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({
    viewport: { width, height },
    ignoreHTTPSErrors: true,
    userAgent: 'Mozilla/5.0 (Stillopress ThemeWizard preview capture)',
    reducedMotion: 'reduce',
  });
  // never follow the page into file:// or block on media
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 }).catch(async () => {
    // fall back to a looser wait if networkidle never settles
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
  });
  await page.waitForTimeout(1200);
  const buf = await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width, height } });
  process.stdout.write(buf.toString('base64'));
  await browser.close();
} catch (e) {
  if (browser) await browser.close().catch(() => {});
  process.stderr.write('ERROR: ' + (e && e.message ? e.message.slice(0, 200) : 'capture failed') + '\n');
  process.exit(1);
}
