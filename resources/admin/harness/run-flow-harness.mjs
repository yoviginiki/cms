// Runs the flow-engine harness in headless Chromium and reports pass/fail.
// Prereq: npx vite build --config vite.harness.config.ts
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';

const here = path.dirname(fileURLToPath(import.meta.url));
const bundle = path.join(here, 'dist', 'flow-harness.js');

const browser = await chromium.launch();
const page = await browser.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));
page.on('console', (m) => {
  if (m.type() === 'error') errors.push(m.text());
});
await page.goto('about:blank');
// about:blank is not a secure context — polyfill randomUUID for the harness
await page.evaluate(() => {
  if (!crypto.randomUUID) {
    crypto.randomUUID = () =>
      'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () =>
        Math.floor(Math.random() * 16).toString(16),
      );
  }
});
await page.addScriptTag({ path: bundle });

const small = await page.evaluate(() => window.runFlowHarness(1200));
const big = await page.evaluate(() => window.runFlowHarness(10000));
// Session E perf gate: the master-doc budget is <500ms for a 10k-word flow.
// Cold run above includes JIT/font warmup; the WARM run is the gated number.
const bigWarm = await page.evaluate(() => window.runFlowHarness(10000));
const shrink = await page.evaluate(() => window.runShrinkHarness());
const exclusion = await page.evaluate(() => window.runExclusionHarness());

console.log('SMALL(1.2k):', JSON.stringify(small));
console.log('BIG(10k):  ', JSON.stringify(big));
console.log('PERF(10k): cold=' + big.ms + 'ms warm=' + bigWarm.ms + 'ms (gate: warm < 500ms)');
console.log('SHRINK:    ', JSON.stringify(shrink));
console.log('EXCLUSION: ', JSON.stringify(exclusion));

const failures = [];
if (!small.lossless) failures.push('small not lossless');
if (small.overset) failures.push('small overset despite pagination');
if (small.pages < 2) failures.push('small did not paginate');
if (!big.lossless) failures.push('big not lossless');
if (big.overset) failures.push('big overset despite pagination');
if (big.pages < 8) failures.push('big paginated too few pages');
if (big.ms > 2000) failures.push(`big flow too slow (cold): ${big.ms}ms`);
if (bigWarm.ms > 500) failures.push(`PERF GATE: warm 10k flow ${bigWarm.ms}ms > 500ms`);
if (!bigWarm.lossless) failures.push('warm big not lossless');
if (!shrink.grow.lossless || !shrink.shrink.lossless) failures.push('shrink losslessness');
if (shrink.pagesAfterShrink >= shrink.pagesAfterGrow) failures.push('shrink did not remove auto pages');
if (shrink.pagesAfterShrink !== 1) failures.push(`expected 1 page after shrink, got ${shrink.pagesAfterShrink}`);
if (exclusion.overlaps !== 0) failures.push('exclusion run lost words');
if (errors.length) failures.push('console errors: ' + errors.join(' | '));

await browser.close();
if (failures.length) {
  console.error('HARNESS FAIL:', failures.join('; '));
  process.exit(1);
}
console.log('HARNESS PASS');
