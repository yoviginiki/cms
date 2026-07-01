/**
 * Stillopress.com — Headless audit (Phase 5)
 * Run: node stillopress-audit.mjs
 */
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';

const BASE = 'https://stillopress.com';
const PAGES = [
  { slug: '/', name: 'Home' },
  { slug: '/features/', name: 'Features' },
  { slug: '/about/', name: 'About' },
  { slug: '/demos/', name: 'Demos' },
  { slug: '/pricing/', name: 'Pricing' },
  { slug: '/docs/', name: 'Docs' },
  { slug: '/contact/', name: 'Contact' },
];
const SHOTS = './stillopress-audit-shots';
mkdirSync(SHOTS, { recursive: true });

const report = { url: BASE, when: new Date().toISOString(), pages: [] };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function tag(status) {
  return { PASS: '  PASS', WARN: '  WARN', FAIL: '  FAIL', INFO: '  INFO' }[status] || status;
}

(async () => {
  const browser = await chromium.launch();
  console.log('\n=== STILLOPRESS.COM AUDIT ===\n');

  // ─── Desktop audit ───
  for (const pg of PAGES) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();

    const consoleErrors = [];
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', (e) => consoleErrors.push('pageerror: ' + e.message));

    const failedRequests = [];
    page.on('response', (res) => { if (res.status() >= 400) failedRequests.push(`${res.status()} ${res.url()}`); });

    const url = BASE + pg.slug;
    console.log(`─── ${pg.name} (${url}) ───`);

    await page.goto(url, { waitUntil: 'load', timeout: 30000 });
    await sleep(500);

    // Screenshot
    await page.screenshot({ path: `${SHOTS}/${pg.name.toLowerCase()}-desktop.png`, fullPage: true });

    // CLS
    await page.evaluate(() => {
      window.__cls = 0;
      try {
        new PerformanceObserver((list) => {
          for (const e of list.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
        }).observe({ type: 'layout-shift', buffered: true });
      } catch (_) {}
    });
    await sleep(1000);
    const cls = await page.evaluate(() => window.__cls || 0);

    // Check key content
    const title = await page.title();
    const hasCanonical = await page.evaluate(() => !!document.querySelector('link[rel="canonical"]'));
    const hasOG = await page.evaluate(() => !!document.querySelector('meta[property="og:title"]'));
    const hasJsonLD = await page.evaluate(() => !!document.querySelector('script[type="application/ld+json"]'));
    const bodyText = await page.evaluate(() => document.body.innerText.length);
    const scriptCount = await page.evaluate(() => document.querySelectorAll('script:not([type="application/ld+json"])').length);
    const externalJS = await page.evaluate(() => document.querySelectorAll('script[src]').length);

    // Check sections render
    const sections = await page.evaluate(() => document.querySelectorAll('.section-block').length);
    const headings = await page.evaluate(() => document.querySelectorAll('h1, h2, h3').length);

    const checks = [];
    const add = (name, status, detail) => {
      checks.push({ name, status, detail });
      console.log(`${tag(status)}  ${name}${detail ? ' — ' + detail : ''}`);
    };

    add('Page loads', 'PASS', `${title} (${bodyText} chars)`);
    add('Console errors', consoleErrors.length === 0 ? 'PASS' : 'FAIL', consoleErrors.length ? consoleErrors.slice(0, 3).join(' | ') : 'none');
    add('Failed requests', failedRequests.length === 0 ? 'PASS' : 'WARN', failedRequests.length ? failedRequests.slice(0, 3).join(', ') : 'none');
    add('CLS', cls < 0.1 ? 'PASS' : cls < 0.25 ? 'WARN' : 'FAIL', cls.toFixed(4));
    add('Canonical URL', hasCanonical ? 'PASS' : 'FAIL', '');
    add('Open Graph', hasOG ? 'PASS' : 'FAIL', '');
    add('JSON-LD', hasJsonLD ? 'PASS' : 'FAIL', '');
    add('Sections rendered', sections > 0 ? 'PASS' : 'FAIL', `${sections} sections, ${headings} headings`);
    add('No external JS', externalJS === 0 ? 'PASS' : 'INFO', `${externalJS} external scripts`);
    add('Minimal inline JS', scriptCount <= 2 ? 'PASS' : 'WARN', `${scriptCount} script tags (excl JSON-LD)`);

    report.pages.push({ page: pg.name, url, checks });
    await ctx.close();
    console.log('');
  }

  // ─── Mobile audit ───
  console.log('─── MOBILE (390×844) ───');
  const mCtx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });

  for (const pg of PAGES) {
    const mp = await mCtx.newPage();
    await mp.goto(BASE + pg.slug, { waitUntil: 'load', timeout: 30000 });
    await sleep(500);

    const overflow = await mp.evaluate(() => document.scrollingElement.scrollWidth - window.innerWidth);
    await mp.screenshot({ path: `${SHOTS}/${pg.name.toLowerCase()}-mobile.png`, fullPage: true });

    const status = overflow <= 2 ? 'PASS' : 'FAIL';
    console.log(`${tag(status)}  ${pg.name} — horizontal overflow: ${overflow}px`);
    report.pages.push({ page: `${pg.name} (mobile)`, checks: [{ name: 'No horizontal overflow', status, detail: `${overflow}px` }] });

    await mp.close();
  }
  await mCtx.close();

  // ─── Slow 3G throttle test (home page) ───
  console.log('\n─── SLOW 3G — Home page ───');
  const slowCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const slowPage = await slowCtx.newPage();

  // Simulate Slow 3G via CDP
  const cdp = await slowCtx.newCDPSession(slowPage);
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false,
    downloadThroughput: 50 * 1024 / 8,   // 50 kbps
    uploadThroughput: 20 * 1024 / 8,      // 20 kbps
    latency: 2000,                         // 2s RTT
  });

  const t0 = Date.now();
  try {
    await slowPage.goto(BASE + '/', { waitUntil: 'load', timeout: 120000 });
    const loadTime = Date.now() - t0;
    const visible = await slowPage.evaluate(() => document.body.innerText.length > 100);

    console.log(`${tag('INFO')}  Load time: ${(loadTime / 1000).toFixed(1)}s`);
    console.log(`${tag(visible ? 'PASS' : 'FAIL')}  Content visible: ${visible}`);
    await slowPage.screenshot({ path: `${SHOTS}/home-slow3g.png` });

    report.pages.push({ page: 'Home (Slow 3G)', checks: [
      { name: 'Load time', status: loadTime < 30000 ? 'PASS' : 'WARN', detail: `${(loadTime / 1000).toFixed(1)}s` },
      { name: 'Content visible', status: visible ? 'PASS' : 'FAIL', detail: '' },
    ]});
  } catch (e) {
    console.log(`${tag('FAIL')}  Slow 3G load failed: ${e.message}`);
    report.pages.push({ page: 'Home (Slow 3G)', checks: [{ name: 'Load', status: 'FAIL', detail: e.message }] });
  }
  await slowCtx.close();

  // ─── Reduced motion test ───
  console.log('\n─── REDUCED MOTION ───');
  const rmCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  const rmPage = await rmCtx.newPage();
  await rmPage.goto(BASE + '/', { waitUntil: 'load' });
  await sleep(500);

  const rmVisible = await rmPage.evaluate(() => {
    const els = Array.from(document.querySelectorAll('h1,h2,h3,p')).slice(0, 15);
    const hidden = els.filter((h) => {
      const s = getComputedStyle(h);
      return parseFloat(s.opacity) < 0.05 || s.visibility === 'hidden';
    }).length;
    return { total: els.length, hidden };
  });
  console.log(`${tag(rmVisible.hidden === 0 ? 'PASS' : 'FAIL')}  All content visible: ${rmVisible.hidden}/${rmVisible.total} hidden`);
  await rmPage.screenshot({ path: `${SHOTS}/home-reduced-motion.png` });
  await rmCtx.close();

  await browser.close();

  // ─── Summary ───
  let pass = 0, warn = 0, fail = 0, info = 0;
  report.pages.forEach((p) => p.checks.forEach((c) => {
    if (c.status === 'PASS') pass++;
    else if (c.status === 'WARN') warn++;
    else if (c.status === 'FAIL') fail++;
    else info++;
  }));

  console.log(`\n=== SUMMARY  PASS:${pass}  WARN:${warn}  FAIL:${fail}  INFO:${info} ===`);
  console.log(`Screenshots in ${SHOTS}/\n`);

  report.summary = { pass, warn, fail, info };
  writeFileSync('stillopress-audit-report.json', JSON.stringify(report, null, 2));
  process.exit(fail > 0 ? 1 : 0);
})().catch((e) => { console.error('AUDIT CRASHED:', e); process.exit(2); });
