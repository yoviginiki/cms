// Canvas editor — Phase 1 Playwright check: desktop freeform, mobile stack, Slow-3G.
// Run: node canvas/canvas.spec.mjs   (requires: npx playwright install chromium)
import { chromium, devices } from 'playwright';

const fileUrl = 'file://' + new URL('./fixture.html', import.meta.url).pathname;
const SLOW_3G = { downloadThroughput: (500 * 1024) / 8, uploadThroughput: (500 * 1024) / 8, latency: 400 };

function ok(name, cond) { console.log(`${cond ? 'PASS' : 'FAIL'}  ${name}`); if (!cond) process.exitCode = 1; }

const browser = await chromium.launch();

// --- Desktop 1440 (freeform) ---
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.emulateNetworkConditions', { offline: false, ...SLOW_3G }); // Slow-3G
  await page.goto(fileUrl, { waitUntil: 'networkidle' });
  const els = page.locator('.cv-el');
  const first = await els.nth(0).boundingBox();
  const second = await els.nth(1).boundingBox();
  // freeform: elements are NOT full-width and NOT simply stacked (they overlap horizontally / differ in x)
  ok('desktop: elements are narrower than viewport (freeform, not full-width)', first.width < 1000);
  ok('desktop: elements have distinct x positions (freeform)', Math.abs(first.x - second.x) > 20);
  await ctx.close();
}

// --- Mobile 390 (auto-stack) ---
{
  const ctx = await browser.newContext({ ...devices['Pixel 5'], viewport: { width: 390, height: 844 } });
  const page = await ctx.newPage();
  await page.goto(fileUrl, { waitUntil: 'networkidle' });
  const els = page.locator('.cv-el');
  const n = await els.count();
  const a = await els.nth(0).boundingBox();
  const b = await els.nth(1).boundingBox();
  ok('mobile: first element is ~full width (stacked flow)', a.width > 320);
  ok('mobile: elements stack vertically (second below first, no overlap)', b.y >= a.y + a.height - 4);
  ok('mobile: rendered element count matches', n >= 3);
  await ctx.close();
}

await browser.close();
console.log('done');
