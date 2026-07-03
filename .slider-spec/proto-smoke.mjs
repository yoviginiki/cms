import { chromium } from 'playwright';
const url = 'file://' + process.cwd() + '/.slider-spec/slider-reference-prototype.html';
const results = [];
const check = (n, ok, d = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}${d ? ' — ' + d : ''}`); };

const browser = await chromium.launch();

// ── 1. normal run: console errors, first slide IN completes, rapid-advance stress ──
let ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
let page = await ctx.newPage();
const errors = [];
page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', e => errors.push(e.message));
await page.goto(url, { waitUntil: 'networkidle' });
await page.waitForTimeout(2600);
check('no console errors on load', errors.length === 0, errors.slice(0,2).join(' | '));

const headlineOpacity = await page.$eval('[data-layer-id="s1-headline"]', el => getComputedStyle(el).opacity);
const headlineVis = await page.$eval('[data-layer-id="s1-headline"]', el => getComputedStyle(el).visibility);
check('slide-1 headline reaches final state after IN', headlineOpacity === '1' && headlineVis === 'visible', `opacity=${headlineOpacity}`);
const chars = await page.$$eval('[data-layer-id="s1-headline"] .sp-char', els => els.length);
check('split(chars) produced char spans', chars > 8, `${chars} chars`);

// rapid-advance 10x
for (let i = 0; i < 10; i++) { await page.click('.sp-arrow.-next'); await page.waitForTimeout(90); }
await page.waitForTimeout(2500);
const broken = await page.evaluate(() => {
  const sw = window.__slider.swiper;
  const active = sw.slides[sw.activeIndex];
  const bad = [];
  active.querySelectorAll('[data-layer-id]').forEach(el => {
    const cs = getComputedStyle(el);
    if (parseFloat(cs.opacity) < 0.99 || cs.visibility !== 'visible') bad.push(el.dataset.layerId + ':' + cs.opacity);
  });
  return bad;
});
check('rapid-advance 10x leaves no half-animated layers', broken.length === 0, broken.join(','));
const orphans = await page.evaluate(() => gsap.globalTimeline.getChildren(true, true, true)
  .filter(t => t.isActive() && !t.vars.repeat && t.vars.repeat !== -1).length);
check('no runaway non-loop tweens after settle', orphans === 0, `${orphans} active`);
await ctx.close();

// ── 2. reduced motion ──
ctx = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 900 } });
page = await ctx.newPage();
await page.goto(url, { waitUntil: 'networkidle' });
await page.waitForTimeout(400);
const rmOpacity = await page.$eval('[data-layer-id="s1-headline"]', el => getComputedStyle(el).opacity);
check('reduced-motion: content instant at final state', rmOpacity === '1');
await page.click('.sp-arrow.-next'); await page.waitForTimeout(600);
const rmSlide2 = await page.evaluate(() => {
  const sw = window.__slider.swiper;
  const el = sw.slides[sw.activeIndex].querySelector('[data-layer-id="s2-display"]');
  return el ? getComputedStyle(el).opacity : 'missing';
});
check('reduced-motion: still navigable', rmSlide2 === '1', `s2 opacity=${rmSlide2}`);
await ctx.close();

// ── 3. JS disabled ──
ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 900 } });
page = await ctx.newPage();
await page.goto(url);
await page.waitForTimeout(800);
const nojsVisible = await page.$eval('[data-layer-id="s1-headline"]', el => {
  const cs = getComputedStyle(el); const r = el.getBoundingClientRect();
  return cs.visibility === 'visible' && parseFloat(cs.opacity) === 1 && r.height > 10;
});
const nojsSlide2Hidden = await page.$eval('[data-slide-id="s2"]', el => getComputedStyle(el).display === 'none');
check('no-JS: slide 1 fully readable', nojsVisible);
check('no-JS: later slides hidden', nojsSlide2Hidden);
await ctx.close();

await browser.close();
console.log(`\nSUMMARY: ${results.filter(Boolean).length}/${results.length} passed`);
process.exit(results.every(Boolean) ? 0 : 1);
