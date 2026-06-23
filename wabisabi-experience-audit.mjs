/*
  wabisabi-experience-audit.mjs
  Headless browser audit of the Cinematic layout runtime.
  Run: node wabisabi-experience-audit.mjs
*/

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';

const URL_FORCE = 'https://ensodo.eu/wabisabi4/?experience=force';
const URL_PLAIN = 'https://ensodo.eu/wabisabi4/';
const SHOTS = './audit-shots';
mkdirSync(SHOTS, { recursive: true });

const report = { url: URL_FORCE, when: new Date().toISOString(), checks: [] };
const add = (name, status, detail) => {
  report.checks.push({ name, status, detail });
  const tag = { PASS: '  PASS', WARN: '  WARN', FAIL: '  FAIL', INFO: '  INFO' }[status] || status;
  console.log(`${tag}  ${name}${detail ? ' — ' + detail : ''}`);
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await chromium.launch();

  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  const consoleErrors = [];
  const consoleWarns = [];
  page.on('console', (m) => {
    if (m.type() === 'error') consoleErrors.push(m.text());
    if (m.type() === 'warning') consoleWarns.push(m.text());
  });
  page.on('pageerror', (e) => consoleErrors.push('pageerror: ' + e.message));

  const requests = [];
  page.on('response', (res) => requests.push({ url: res.url(), status: res.status() }));

  console.log('\n=== CINEMATIC EXPERIENCE AUDIT — wabisabi4 ===\n');

  await page.goto(URL_FORCE, { waitUntil: 'load', timeout: 45000 });
  await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
  // Wait for preloader to finish (3s count + 0.8s fade + 0.5s delay + 1s buffer)
  await sleep(6000);

  const env = await page.evaluate(() => ({
    gsap: typeof window.gsap !== 'undefined',
    st: typeof window.ScrollTrigger !== 'undefined',
    stCount: (window.ScrollTrigger && window.ScrollTrigger.getAll) ? window.ScrollTrigger.getAll().length : 0,
    lenis: typeof window.Lenis !== 'undefined' || !!document.documentElement.className.match(/lenis/),
    swiper: typeof window.Swiper !== 'undefined' || !!document.querySelector('.swiper'),
    scenes: Array.from(document.querySelectorAll('[data-scene]')).map((e) => e.getAttribute('data-scene')),
    pinSpacers: document.querySelectorAll('.pin-spacer').length,
    sections: document.querySelectorAll('section, [data-section], .section').length,
  }));

  add('Runtime bundle loaded', env.gsap && env.st ? 'PASS' : 'FAIL',
    `gsap=${env.gsap} ScrollTrigger=${env.st}`);
  add('ScrollTrigger instances wired', env.stCount > 0 ? 'PASS' : 'FAIL',
    `${env.stCount} triggers`);
  add('Pinned scenes present', env.pinSpacers > 0 ? 'PASS' : 'WARN',
    `${env.pinSpacers} pin-spacers`);
  add('Scene presets applied', env.scenes.length ? 'INFO' : 'WARN',
    env.scenes.length ? env.scenes.join(', ') : 'no [data-scene] attributes found');
  add('scroll-gallery signature scene', env.scenes.includes('scroll-gallery') ? 'PASS' : 'FAIL',
    env.scenes.includes('scroll-gallery') ? 'present' : 'MISSING');
  add('Swiper gallery', env.swiper ? 'PASS' : 'INFO', env.swiper ? 'present' : 'not detected (images may be stacked)');
  add('Smooth-scroll (Lenis)', env.lenis ? 'INFO' : 'INFO', env.lenis ? 'active' : 'not detected (deferrable)');

  add('Console errors', consoleErrors.length === 0 ? 'PASS' : 'FAIL',
    consoleErrors.length ? consoleErrors.slice(0, 5).join(' | ') : 'none');
  add('Console warnings', consoleWarns.length === 0 ? 'PASS' : 'WARN',
    consoleWarns.length ? `${consoleWarns.length} (first: ${consoleWarns[0]})` : 'none');

  const bad = requests.filter((r) => r.status >= 400);
  add('No failed asset requests', bad.length === 0 ? 'PASS' : 'WARN',
    bad.length ? bad.slice(0, 4).map((b) => `${b.status} ${b.url.split('/').pop()}`).join(', ') : 'all 200/30x');

  await page.evaluate(() => {
    window.__cls = 0;
    try {
      new PerformanceObserver((list) => {
        for (const e of list.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
      }).observe({ type: 'layout-shift', buffered: true });
    } catch (_) {}
  });

  const height = await page.evaluate(() => document.body.scrollHeight);
  for (const pct of [0, 25, 50, 75, 100]) {
    await page.evaluate((p) => window.scrollTo(0, (document.body.scrollHeight - innerHeight) * p / 100), pct);
    await sleep(900);
    await page.screenshot({ path: `${SHOTS}/scroll-${pct}.png` });
  }
  const cls = await page.evaluate(() => window.__cls || 0);
  add('Cumulative Layout Shift', cls < 0.1 ? 'PASS' : cls < 0.25 ? 'WARN' : 'FAIL', cls.toFixed(3));

  const pinHealth = await page.evaluate(async () => {
    const spacer = document.querySelector('.pin-spacer');
    if (!spacer) return { ok: null, note: 'no pinned section' };
    const pinned = spacer.querySelector(':scope > *') || spacer.firstElementChild;
    if (!pinned) return { ok: null, note: 'no pinned child' };
    const top = spacer.getBoundingClientRect().top + window.scrollY;
    const tops = [];
    for (let i = 0; i < 6; i++) {
      window.scrollTo(0, top + i * 60);
      await new Promise((r) => setTimeout(r, 120));
      tops.push(Math.round(pinned.getBoundingClientRect().top));
    }
    const spread = Math.max(...tops) - Math.min(...tops);
    return { ok: spread < 24, note: `pinned top drift ${spread}px during pin range`, tops };
  });
  add('Pin holds during its range', pinHealth.ok === null ? 'INFO' : pinHealth.ok ? 'PASS' : 'FAIL', pinHealth.note);

  const refreshDelta = await page.evaluate(async () => {
    if (!window.ScrollTrigger) return null;
    const before = window.ScrollTrigger.getAll().map((s) => Math.round(s.start));
    window.ScrollTrigger.refresh();
    await new Promise((r) => setTimeout(r, 300));
    const after = window.ScrollTrigger.getAll().map((s) => Math.round(s.start));
    let maxd = 0;
    for (let i = 0; i < Math.min(before.length, after.length); i++)
      maxd = Math.max(maxd, Math.abs(before[i] - after[i]));
    return maxd;
  });
  if (refreshDelta !== null)
    add('Trigger positions stable (refresh discipline)', refreshDelta < 40 ? 'PASS' : 'FAIL',
      `max start shift on refresh = ${refreshDelta}px${refreshDelta >= 40 ? ' — runtime likely not refreshing after media/fonts load' : ''}`);

  const rmCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  const rm = await rmCtx.newPage();
  await rm.goto(URL_FORCE, { waitUntil: 'load' });
  await sleep(1500);
  const rmVisible = await rm.evaluate(() => {
    const heads = Array.from(document.querySelectorAll('h1,h2,h3,p')).slice(0, 12);
    const hidden = heads.filter((h) => {
      const s = getComputedStyle(h);
      return parseFloat(s.opacity) < 0.05 || s.visibility === 'hidden';
    }).length;
    return { total: heads.length, hidden };
  });
  await rm.screenshot({ path: `${SHOTS}/reduced-motion.png` });
  add('Reduced-motion shows static content', rmVisible.hidden === 0 ? 'PASS' : 'FAIL',
    `${rmVisible.hidden}/${rmVisible.total} key elements stuck hidden`);
  await rmCtx.close();

  const mCtx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
  const mp = await mCtx.newPage();
  await mp.goto(URL_FORCE, { waitUntil: 'load' });
  await sleep(1200);
  const overflow = await mp.evaluate(() => document.scrollingElement.scrollWidth - window.innerWidth);
  await mp.screenshot({ path: `${SHOTS}/mobile.png` });
  add('No horizontal overflow (mobile)', overflow <= 2 ? 'PASS' : 'FAIL', `${overflow}px overflow`);
  await mCtx.close();

  const pPlain = await ctx.newPage();
  await pPlain.goto(URL_PLAIN, { waitUntil: 'load' });
  await sleep(1500);
  const plainOn = await pPlain.evaluate(() =>
    typeof window.ScrollTrigger !== 'undefined' && window.ScrollTrigger.getAll().length > 0);
  add('Experience fires WITHOUT ?experience=force', plainOn ? 'PASS' : 'FAIL',
    plainOn ? 'real visitors get it' : 'only the debug flag triggers it — real visitors see a plain page');

  await ctx.close();
  await browser.close();

  const tally = report.checks.reduce((a, c) => ((a[c.status] = (a[c.status] || 0) + 1), a), {});
  console.log(`\n=== SUMMARY  PASS:${tally.PASS||0}  WARN:${tally.WARN||0}  FAIL:${tally.FAIL||0}  INFO:${tally.INFO||0} ===`);
  console.log(`Screenshots in ${SHOTS}/  ·  full report: experience-audit-report.json\n`);
  report.summary = tally;
  writeFileSync('experience-audit-report.json', JSON.stringify(report, null, 2));
  process.exit((tally.FAIL || 0) > 0 ? 1 : 0);
})().catch((e) => { console.error('AUDIT CRASHED:', e); process.exit(2); });
