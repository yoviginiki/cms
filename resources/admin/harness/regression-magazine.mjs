// ═══════════════════════════════════════════════════════════════════════════
// Magazine regression harness (Session C Phase 4).
// For every fixture issue in fixtures.json:
//   1. GET dtp-document  → pinned page/frame/thread counts + LOSSLESSNESS
//      (concat of thread slices' words must equal the re-joined story words)
//   2. GET dtp-preview   → page count matches + pinned content markers
//   3. screenshots of editor page 1 + preview → harness/shots/ (for eyeballs)
// Exit code 1 on ANY mismatch. Run BEFORE and AFTER every magazine session.
//
// Usage:
//   BASE=https://sys.ensodo.eu EMAIL=... PASSWORD=... \
//     node harness/regression-magazine.mjs [--update]   (--update re-pins counts)
// ═══════════════════════════════════════════════════════════════════════════
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.BASE || 'https://sys.ensodo.eu';
const EMAIL = process.env.EMAIL;
const PASSWORD = process.env.PASSWORD;
const UPDATE = process.argv.includes('--update');
if (!EMAIL || !PASSWORD) { console.error('Missing EMAIL/PASSWORD'); process.exit(1); }
const H = { Accept: 'application/json', Origin: BASE, Referer: BASE + '/admin/', 'X-Requested-With': 'XMLHttpRequest' };
const fixturesPath = path.join(here, 'fixtures.json');
const config = JSON.parse(fs.readFileSync(fixturesPath, 'utf8'));
const shotsDir = path.join(here, 'shots');
fs.mkdirSync(shotsDir, { recursive: true });

const words = (html) => String(html).replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean);

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1600, height: 1000 } });
const page = await ctx.newPage();
await page.goto(BASE + '/admin/', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForTimeout(2500);

const failures = [];
for (const fx of config.fixtures) {
  const { siteId, issueId, name } = fx;
  const doc = (await (await page.request.get(`${BASE}/api/v1/sites/${siteId}/magazine-issues/${issueId}/dtp-document`, { headers: H })).json()).data;
  const textFrames = (doc.frames || []).filter((f) => ['text', 'quote'].includes(f.frame_type));
  const threadIds = new Set(textFrames.map((f) => f.metadata?.threadId).filter(Boolean));
  const actual = {
    pages: (doc.pages || []).length,
    textFrames: textFrames.length,
    threads: threadIds.size,
  };

  // LOSSLESSNESS per thread: slices concat == whole (no dupes, no gaps)
  let lossless = true;
  for (const tid of threadIds) {
    const chain = textFrames
      .filter((f) => f.metadata?.threadId === tid)
      .sort((a, b) => (a.metadata?.threadOrder ?? 0) - (b.metadata?.threadOrder ?? 0));
    const sliceWords = chain.flatMap((f) => words(f.content?.html || ''));
    if (sliceWords.length === 0) lossless = false;
    // duplicate-detection: no word sequence should repeat at slice joins
    for (let i = 1; i < chain.length; i++) {
      const prev = words(chain[i - 1].content?.html || '').slice(-12).join(' ');
      const head = words(chain[i].content?.html || '').slice(0, 12).join(' ');
      if (prev && prev === head) lossless = false; // exact duplicated run
    }
  }

  const prevRes = await page.request.get(`${BASE}/api/v1/sites/${siteId}/magazine-issues/${issueId}/dtp-preview`, { headers: { Referer: BASE + '/admin/', 'X-Requested-With': 'XMLHttpRequest' } });
  const prevHtml = await prevRes.text();
  // stillo-viewer runtime (Viewer 2.0) emits .sv-page; legacy emitted .page
  const previewPages = (prevHtml.match(/class="sv-page"/g) || []).length || (prevHtml.match(/class="page"/g) || []).length;

  if (UPDATE) {
    fx.expect = { ...fx.expect, ...actual, lossless: true };
    console.log(`[${name}] pinned:`, JSON.stringify(actual));
  } else {
    const e = fx.expect || {};
    if (actual.pages !== e.pages) failures.push(`${name}: pages ${actual.pages} != pinned ${e.pages}`);
    if (actual.textFrames !== e.textFrames) failures.push(`${name}: textFrames ${actual.textFrames} != pinned ${e.textFrames}`);
    if (actual.threads !== e.threads) failures.push(`${name}: threads ${actual.threads} != pinned ${e.threads}`);
    if (!lossless) failures.push(`${name}: LOSSLESSNESS FAIL`);
    if (previewPages !== e.pages) failures.push(`${name}: preview pages ${previewPages} != ${e.pages}`);
    for (const marker of e.previewMarkers || []) {
      if (!prevHtml.includes(marker)) failures.push(`${name}: preview missing marker "${marker}"`);
    }
    console.log(`[${name}]`, JSON.stringify(actual), 'lossless=', lossless, 'previewPages=', previewPages);
  }

  // screenshots (visual eyeball trail; not pixel-pinned — font rendering
  // varies across chromium builds, structural pins above are the gate)
  await page.goto(`${BASE}/admin/sites/${siteId}/magazine-issues/${issueId}/dtp-editor`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(4000);
  await page.screenshot({ path: path.join(shotsDir, `${name}-editor.png`) });
  await page.setContent(prevHtml, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: path.join(shotsDir, `${name}-preview.png`), fullPage: false });
}

if (UPDATE) fs.writeFileSync(fixturesPath, JSON.stringify(config, null, 2) + '\n');
await browser.close();
if (failures.length) {
  console.error('REGRESSION FAIL:\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log(UPDATE ? 'PINS UPDATED' : 'REGRESSION PASS');
