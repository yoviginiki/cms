// ═══════════════════════════════════════════════════════════════════════════
// Magazine editor MONKEY TEST: N random interleaved operations (clicks,
// drags, marquees, undo/redo storms, group cycles, tool switches, inline
// typing, alt-drag dups, saves, guide drags) driven by a SEEDED PRNG so any
// failure is reproducible from the seed. Fails on ANY page/console error.
// Usage: BASE=… EMAIL=… PASSWORD=… SITE_ID=… ISSUE_ID=… [SEED=…] [OPS=…] \
//          node harness/monkey-magazine.mjs   (use a DISPOSABLE issue!)
// ═══════════════════════════════════════════════════════════════════════════
import { chromium } from 'playwright';
import fs from 'fs';
const BASE = process.env.BASE || 'https://sys.ensodo.eu';
const SID = process.env.SITE_ID, IID = process.env.ISSUE_ID, PW = process.env.PASSWORD, EMAIL = process.env.EMAIL;
if (!SID || !IID || !PW || !EMAIL) { console.error('need EMAIL/PASSWORD/SITE_ID/ISSUE_ID'); process.exit(1); }
// seeded PRNG so failures are REPRODUCIBLE
let seed = Number(process.env.SEED || 424242);
const rnd = () => (seed = (seed * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff;
const browser = await chromium.launch();
const page = await (await browser.newContext({ ignoreHTTPSErrors: true, viewport:{width:1700,height:1000} })).newPage();
page.setDefaultTimeout(2500);
const errors = [];
page.on('pageerror', e => errors.push(String(e).slice(0,140)));
page.on('console', m => { if (m.type() === 'error' && !/40[134]|Failed to load resource/.test(m.text())) errors.push('console: ' + m.text().slice(0,140)); });
await page.goto(BASE+'/admin/',{waitUntil:'networkidle'});
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]',PW);
await page.click('button[type="submit"]');
await page.waitForTimeout(2500);
await page.goto(`${BASE}/admin/sites/${SID}/magazine-issues/${IID}/dtp-editor`,{waitUntil:'networkidle'});
await page.waitForTimeout(4000);
// seed content: a few elements from the palette
for (const item of ['Text frame', 'Headline', 'Pull quote', 'Rectangle', 'Text on path']) {
  await page.getByRole('button', { name: '+ Add', exact: true }).click().catch(()=>{});
  await page.waitForTimeout(300);
  await page.getByText(item, { exact: true }).first().click().catch(()=>{});
  await page.waitForTimeout(300);
}
const cv = { x: 350, y: 200, w: 700, h: 600 }; // canvas region
const P = (n) => Math.floor(rnd() * n);
const ops = [];
const OPS = [
  async () => { ops.push('click'); await page.mouse.click(cv.x + P(cv.w), cv.y + P(cv.h)); },
  async () => { ops.push('drag'); const x=cv.x+P(cv.w), y=cv.y+P(cv.h); await page.mouse.move(x,y); await page.mouse.down(); await page.mouse.move(x+P(160)-80, y+P(160)-80, {steps:3}); await page.mouse.up(); },
  async () => { ops.push('marquee'); const x=cv.x+P(300), y=cv.y+P(300); await page.mouse.move(x,y); await page.mouse.down(); await page.mouse.move(x+150+P(200), y+120+P(200), {steps:3}); await page.mouse.up(); },
  async () => { ops.push('undo'); await page.keyboard.press('Control+z'); },
  async () => { ops.push('redo'); await page.keyboard.press('Control+y'); },
  async () => { ops.push('dup'); await page.keyboard.press('Control+d'); },
  async () => { ops.push('selall+group'); await page.keyboard.press('Control+a'); await page.keyboard.press('Control+g'); },
  async () => { ops.push('ungroup'); await page.keyboard.press('Control+Shift+g'); },
  async () => { ops.push('nudge'); await page.keyboard.press(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'][P(4)]); },
  async () => { ops.push('tool'); await page.keyboard.press(['v','t','r','e'][P(4)]); },
  async () => { ops.push('esc'); await page.keyboard.press('Escape'); },
  async () => { ops.push('preview'); await page.keyboard.press('w'); await page.waitForTimeout(300); await page.keyboard.press('w'); },
  async () => { ops.push('rclick'); await page.mouse.click(cv.x+P(cv.w), cv.y+P(cv.h), {button:'right'}); await page.waitForTimeout(200); await page.keyboard.press('Escape'); },
  async () => { ops.push('dblclick+type'); await page.mouse.dblclick(cv.x+P(cv.w), cv.y+P(cv.h)); await page.keyboard.type('qa'); await page.keyboard.press('Escape'); },
  async () => { ops.push('alt+drag'); const x=cv.x+P(cv.w), y=cv.y+P(cv.h); await page.keyboard.down('Alt'); await page.mouse.move(x,y); await page.mouse.down(); await page.mouse.move(x+60,y+40,{steps:2}); await page.mouse.up(); await page.keyboard.up('Alt'); },
  async () => { ops.push('save'); await page.getByRole('button', { name: /^save/i }).first().click({timeout:5000}).catch(()=>{}); await page.waitForTimeout(2500); },
  async () => { ops.push('guide-drag'); const r = page.locator('[title*="horizontal guide"]').first(); const b = await r.boundingBox().catch(()=>null); if (b) { await page.mouse.move(b.x+200+P(200), b.y+8); await page.mouse.down(); await page.mouse.move(b.x+200, b.y+150+P(300), {steps:3}); await page.mouse.up(); } },
];
const N = Number(process.env.OPS || 80);
for (let i = 0; i < N; i++) {
  try { await OPS[P(OPS.length)](); } catch (e) { /* op-level timeouts fine */ }
  await page.waitForTimeout(60);
  if (errors.length) { console.log(`ERROR after op ${i} (${ops[ops.length-1]}):`, errors[0]); break; }
}
console.log(`MONKEY: ${N} ops (seed ${process.env.SEED || 424242}), errors=${errors.length}`);
if (errors.length) errors.slice(0,5).forEach(e => console.log(' -', e));
else console.log('CLEAN — no page errors, no console errors across the storm');
await page.screenshot({ path: 'harness/shots/monkey-final.png' }).catch(()=>{});
await browser.close();
process.exit(errors.length ? 1 : 0);
