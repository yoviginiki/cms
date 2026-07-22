/**
 * Deterministic URL → page-manifest importer for the Page Wizard (NO AI).
 * Loads a URL in a headless browser, reads the REAL DOM + on-screen geometry,
 * and emits a Stillopress page manifest ({page_title, design_read, blocks[]})
 * using the real headings, paragraphs, images, and CTAs.
 *
 * The in-page extractor lives in scripts/lib/page-extract.mjs (shared with the
 * Site Wizard's import-site-page.mjs) — output shape unchanged.
 *
 * Usage: node scripts/import-url.mjs <url>
 * Prints the manifest JSON to stdout, or "ERROR: <msg>" to stderr + exit 1.
 * SSRF is guarded by the PHP caller BEFORE this runs.
 */
import { chromium } from 'playwright';
import { PAGE_EXTRACTOR, autoScroll } from './lib/page-extract.mjs';

const url = process.argv[2];
if (!url || !/^https?:\/\//i.test(url)) {
  process.stderr.write('ERROR: a http(s) URL is required\n');
  process.exit(1);
}

let browser;
try {
  browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    ignoreHTTPSErrors: true,
    userAgent: 'Mozilla/5.0 (Stillopress Wizard import)',
    reducedMotion: 'reduce',
  });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 }).catch(async () => {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
  });
  await page.waitForTimeout(1000);
  await autoScroll(page);

  const manifest = await page.evaluate(PAGE_EXTRACTOR);
  manifest.design_read = `Imported ${manifest.blocks.length} section(s) from ${manifest.hostname}.`;
  delete manifest.hostname;

  if (!manifest.blocks.length) {
    process.stderr.write('ERROR: no importable content found on that page\n');
    process.exit(1);
  }

  process.stdout.write(JSON.stringify(manifest));
  await browser.close();
} catch (e) {
  if (browser) await browser.close().catch(() => {});
  process.stderr.write('ERROR: ' + (e && e.message ? e.message.slice(0, 200) : 'import failed') + '\n');
  process.exit(1);
}
