import { chromium } from 'playwright';
const URL = 'https://ensodo.eu/slidertest/';
const results = [];
const check = (n, ok, d='') => { results.push(ok); console.log(`${ok?'PASS':'FAIL'}  ${n}${d?' — '+d:''}`); };

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const errors = [], failed404 = [];
page.on('pageerror', e => errors.push(e.message));
page.on('response', r => { if (r.status() >= 400) failed404.push(`${r.status()} ${r.url().split('/').pop()}`); });

await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('[data-slider-id]').scrollIntoViewIfNeeded();
await page.waitForTimeout(2200);

check('slider armed (runtime initialized)', await page.$eval('[data-slider-id]', el => el.hasAttribute('data-armed')));
const headline = await page.evaluate(() => {
  const el = Array.from(document.querySelectorAll('[data-layer-id]')).find(e => e.textContent.includes('Тишина в движение'));
  return el ? { opacity: getComputedStyle(el).opacity, chars: el.querySelectorAll('.sp-char').length } : null;
});
check('slide-1 headline animated in (split chars)', headline && headline.opacity === '1' && headline.chars > 10, JSON.stringify(headline));
check('no failed asset/runtime requests', failed404.length === 0, failed404.slice(0,3).join(' | '));

// arrows advance to slide 2
await page.click('[data-slider-next]');
await page.waitForTimeout(1300);
const s2 = await page.evaluate(() => {
  const el = Array.from(document.querySelectorAll('[data-layer-id]')).find(e => e.textContent.includes('Ваби-саби'));
  const r = el?.getBoundingClientRect();
  return el && r.width > 0 && r.left >= 0 && r.left < innerWidth ? getComputedStyle(el).opacity : 'not-visible';
});
check('arrow advances; slide-2 IN plays', s2 === '1', `opacity=${s2}`);

// rapid-advance stress on the live page
for (let i = 0; i < 8; i++) { await page.click('[data-slider-next]'); await page.waitForTimeout(90); }
await page.waitForTimeout(2000);
const bad = await page.evaluate(() => {
  const active = document.querySelector('.swiper-slide-active') || document.querySelector('.swiper-slide');
  return Array.from(active.querySelectorAll('[data-layer-id]'))
    .filter(el => parseFloat(getComputedStyle(el).opacity) < 0.99 || getComputedStyle(el).visibility !== 'visible')
    .map(el => el.dataset.layerId);
});
check('rapid-advance leaves no broken layers', bad.length === 0, bad.join(','));

const hrefs = await page.evaluate(() => Array.from(document.querySelectorAll('.sp-layer a')).map(a => a.getAttribute('href')));
check('buttons link to real pages', hrefs.includes('/dzen') && hrefs.includes('/wabisabi') && hrefs.includes('/glasove'), hrefs.join(','));
const bullets = await page.locator('[data-slider-bullet]').count();
check('bullets + counter present', bullets === 3 && (await page.textContent('.sp-counter')).includes('/ 3'));
check('no JS errors', errors.length === 0, errors.slice(0,2).join('|'));

await page.screenshot({ path: process.env.CLAUDE_JOB_DIR + '/tmp/live-slidertest.png' });
await browser.close();
console.log(`\nSUMMARY: ${results.filter(Boolean).length}/${results.length} passed`);
process.exit(results.every(Boolean) ? 0 : 1);
