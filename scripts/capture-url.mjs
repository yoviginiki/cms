/**
 * Capture a reference URL to a PNG for the wizards' vision analysis.
 * Usage: node scripts/capture-url.mjs <url> [widthxheight] [full]
 *   - default: top-viewport clip (Theme Wizard — design tokens from above the fold)
 *   - "full":  the whole page, lazy-content scrolled in, height-capped (Page
 *              Wizard — needs the full layout to replicate structure)
 * Prints base64 PNG to stdout, or "ERROR: <msg>" to stderr with exit 1.
 *
 * SSRF is guarded by the PHP caller (ReferenceCaptureService) BEFORE this runs;
 * this script additionally refuses non-http(s) and caps the wait.
 */
import { chromium } from 'playwright';

const url = process.argv[2];
const dims = (process.argv[3] || '1280x900').split('x').map((n) => parseInt(n, 10));
const [width, height] = [dims[0] || 1280, dims[1] || 900];
const fullPage = process.argv[4] === 'full';
const MAX_FULL_HEIGHT = 6000; // keep the image within vision-model detail limits

if (!url || !/^https?:\/\//i.test(url)) {
  process.stderr.write('ERROR: a http(s) URL is required\n');
  process.exit(1);
}

async function autoScroll(page) {
  // Trigger lazy-loaded images/sections, then return to the top.
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let y = 0;
      const timer = setInterval(() => {
        window.scrollBy(0, 600);
        y += 600;
        if (y >= document.documentElement.scrollHeight - window.innerHeight || y > 20000) {
          clearInterval(timer);
          resolve();
        }
      }, 100);
    });
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(600);
}

let browser;
try {
  browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({
    viewport: { width, height },
    ignoreHTTPSErrors: true,
    userAgent: 'Mozilla/5.0 (Stillopress Wizard preview capture)',
    reducedMotion: 'reduce',
  });
  // never follow the page into file:// or block on media
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 }).catch(async () => {
    // fall back to a looser wait if networkidle never settles
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
  });
  await page.waitForTimeout(1200);

  let buf;
  if (fullPage) {
    await autoScroll(page);
    const h = await page.evaluate((cap) => Math.min(document.documentElement.scrollHeight, cap), MAX_FULL_HEIGHT);
    await page.setViewportSize({ width, height: h });
    await page.waitForTimeout(500);
    buf = await page.screenshot({ type: 'png' });
  } else {
    buf = await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width, height } });
  }

  process.stdout.write(buf.toString('base64'));
  await browser.close();
} catch (e) {
  if (browser) await browser.close().catch(() => {});
  process.stderr.write('ERROR: ' + (e && e.message ? e.message.slice(0, 200) : 'capture failed') + '\n');
  process.exit(1);
}
