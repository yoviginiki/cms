/** Slider Phase 6 — staged audit (a–j) + Slow-3G re-run */
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8099';
const PAGE = `${BASE}/phase6-audit/index.html`;
const results = [];
const check = (n, ok, d = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}${d ? ' — ' + d : ''}`); };

const browser = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] });

const settleActiveLayers = () => {
  const sw = document.querySelector('[data-slider-id]');
  const active = sw.querySelector('.swiper-slide-active') || sw.querySelector('.swiper-slide');
  const bad = [];
  active.querySelectorAll('[data-layer-id]').forEach(el => {
    const cs = getComputedStyle(el);
    if (parseFloat(cs.opacity) < 0.99 || cs.visibility !== 'visible') bad.push(el.dataset.layerId + ':' + cs.opacity);
  });
  return bad;
};

/* ── main run (desktop) ── */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  // CLS observer must be installed before load
  await page.addInitScript(() => {
    window.__cls = 0;
    new PerformanceObserver(list => {
      for (const e of list.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
    }).observe({ type: 'layout-shift', buffered: true });
  });
  const t0 = Date.now();
  await page.goto(PAGE, { waitUntil: 'load', timeout: 60000 });
  const loadMs = Date.now() - t0;
  await page.waitForTimeout(1800);

  // (a) first slide layers final state
  const headline = await page.$eval('[data-layer-id] .text-block, .sp-layer', () => true).catch(() => false);
  const firstBad = await page.evaluate(settleActiveLayers);
  check('(a) first-slide layers reach final opacity/position', firstBad.length === 0, firstBad.join(','));

  // (e) config blob parses
  const cfg = await page.evaluate(() => {
    try { return JSON.parse(document.querySelector('script[data-slider-config]').textContent); }
    catch { return null; }
  });
  check('(e) config blob parses (3 slides)', !!cfg && cfg.slides.length === 3);

  // (b) advancing plays next slide IN (loop clones exist — check the VISIBLE marker)
  await page.click('[data-slider-next]');
  await page.waitForTimeout(1400);
  const s2 = await page.evaluate(() => {
    const els = Array.from(document.querySelectorAll('[data-layer-id]'))
      .filter(e => e.textContent.includes('SLIDE-TWO-MARKER'));
    const visible = els.find(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.left >= 0 && r.left < window.innerWidth; });
    return visible ? getComputedStyle(visible).opacity : `missing(${els.length} clones)`;
  });
  check('(b) advancing plays next slide IN scene', s2 === '1', `opacity=${s2}`);

  // (c) rapid-advance 10x
  for (let i = 0; i < 10; i++) { await page.click('[data-slider-next]'); await page.waitForTimeout(80); }
  await page.waitForTimeout(2000);
  const rapidBad = await page.evaluate(settleActiveLayers);
  check('(c) rapid-advance 10x leaves no half-animated layers', rapidBad.length === 0, rapidBad.join(','));

  // (h) CLS
  const cls = await page.evaluate(() => window.__cls);
  check('(h) no CLS from the slider container', cls < 0.02, `CLS=${cls.toFixed(4)}`);

  // (i) audio: never autoplays; pauses on slide change (drive via UI clicks)
  // rapid test left us somewhere in the loop — click until an audio element is in view
  let audioProbe = { found: false };
  for (let i = 0; i < 4 && !audioProbe.found; i++) {
    audioProbe = await page.evaluate(() => {
      const audios = Array.from(document.querySelectorAll('audio'));
      const visible = audios.find(a => { const r = a.getBoundingClientRect(); return r.width > 0 && r.left >= 0 && r.left < window.innerWidth; });
      return visible ? { found: true, neverAuto: !visible.hasAttribute('autoplay') && visible.paused } : { found: false };
    });
    if (!audioProbe.found) { await page.click('[data-slider-next]'); await page.waitForTimeout(900); }
  }
  check('(i) audio never autoplays', audioProbe.found && audioProbe.neverAuto, JSON.stringify(audioProbe));
  const pauseProbe = await page.evaluate(async () => {
    const audios = Array.from(document.querySelectorAll('audio'));
    const a = audios.find(x => { const r = x.getBoundingClientRect(); return r.width > 0 && r.left >= 0 && r.left < window.innerWidth; }) || audios[0];
    if (!a) return { found: false };
    // play() never settles on a stalled/404 source — time-box it
    await Promise.race([a.play().catch(() => {}), new Promise(r => setTimeout(r, 1500))]);
    return { found: true, playing: !a.paused };
  });
  await page.click('[data-slider-next]');
  await page.waitForTimeout(900);
  const pausedAfter = await page.evaluate(() =>
    Array.from(document.querySelectorAll('audio')).every(a => a.paused));
  check('(i) audio pauses on slide change', pauseProbe.found && (!pauseProbe.playing || pausedAfter),
    JSON.stringify({ ...pauseProbe, pausedAfter }));

  check('no JS errors (main run)', errors.length === 0, errors.slice(0, 2).join('|'));
  await ctx.close();
}

/* ── (f) breakpoint overrides at 3 viewports ── */
for (const [device, width, expectedXPct] of [['desktop', 1440, 8], ['tablet', 900, 8], ['mobile', 390, 4]]) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(PAGE, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(1200);
  const xPct = await page.evaluate(() => {
    const el = Array.from(document.querySelectorAll('[data-layer-id]'))
      .find(e => e.textContent.includes('AUDIT-HEADLINE'));
    const slide = el.closest('.swiper-slide');
    return (parseFloat(getComputedStyle(el).left) / slide.clientWidth) * 100;
  });
  check(`(f) ${device} x-position ≈ ${expectedXPct}%`, Math.abs(xPct - expectedXPct) < 1.5, `${xPct.toFixed(1)}%`);
  await ctx.close();
}

/* ── (g) reduced motion ── */
{
  const ctx = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(PAGE, { waitUntil: 'load', timeout: 60000 });
  await page.waitForTimeout(300);
  const bad = await page.evaluate(settleActiveLayers);
  check('(g) reduced-motion: content instant at final state', bad.length === 0, bad.join(','));
  await ctx.close();
}

/* ── (d) no-JS ── */
{
  const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(PAGE, { timeout: 60000 });
  await page.waitForTimeout(600);
  const state = await page.evaluate(() => {
    const first = document.querySelector('.swiper-slide');
    const headline = first.querySelector('[data-layer-id]');
    const cs = getComputedStyle(headline);
    const second = document.querySelectorAll('.swiper-slide')[1];
    return {
      readable: cs.visibility === 'visible' && parseFloat(cs.opacity) === 1 && headline.getBoundingClientRect().height > 5,
      slide2Hidden: getComputedStyle(second).display === 'none',
    };
  });
  check('(d) no-JS: first slide readable', state.readable);
  check('(d) no-JS: later slides hidden', state.slide2Hidden);
  await ctx.close();
}

/* ── Slow-3G re-run ── */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: 400,
    downloadThroughput: 400 * 1024 / 8, uploadThroughput: 400 * 1024 / 8,
  });
  await page.addInitScript(() => {
    window.__cls = 0;
    new PerformanceObserver(list => {
      for (const e of list.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
    }).observe({ type: 'layout-shift', buffered: true });
  });
  const t0 = Date.now();
  await page.goto(PAGE, { waitUntil: 'domcontentloaded', timeout: 120000 });
  const dclMs = Date.now() - t0;
  await page.waitForLoadState('load', { timeout: 120000 }).catch(() => {});
  const loadMs = Date.now() - t0;
  await page.waitForTimeout(3000);
  const bad = await page.evaluate(settleActiveLayers);
  const cls = await page.evaluate(() => window.__cls);
  const eager = await page.evaluate(() => {
    const img = document.querySelector('.swiper-slide .sp-bg img');
    return img ? { fetchpriority: img.getAttribute('fetchpriority'), loading: img.getAttribute('loading') } : null;
  });
  check('slow-3G: no missing/half-animated layers after settle', bad.length === 0, bad.join(','));
  check('slow-3G: no layout shift', cls < 0.02, `CLS=${cls.toFixed(4)}`);
  check('slow-3G: first-slide media eager (LCP)', eager && eager.fetchpriority === 'high' && eager.loading !== 'lazy', JSON.stringify(eager));
  console.log(`TIMING slow-3G: DCL ${dclMs}ms, load ${loadMs}ms`);
  await ctx.close();
}

await browser.close();
console.log(`\nSUMMARY: ${results.filter(Boolean).length}/${results.length} passed`);
process.exit(results.every(Boolean) ? 0 : 1);
