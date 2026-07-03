import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'node:fs';
const creds = JSON.parse(readFileSync(process.env.CLAUDE_JOB_DIR + '/tmp/s4_creds.json', 'utf8').trim().split('\n').pop());
const SITE = '019e32f5-0000-0000-0000-000000000001';
const PAGEID = '019f2917-ca61-7023-a54c-36f6e72773ac';
const BASE = 'https://sys.ensodo.eu';
const SHOTS = process.env.CLAUDE_JOB_DIR + '/tmp/s4-shots'; mkdirSync(SHOTS, { recursive: true });
const results = [];
const check = (n, ok, d='') => { results.push(ok); console.log(`${ok?'PASS':'FAIL'}  ${n}${d?' — '+d:''}`); };

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', creds.email);
await page.fill('input[type="password"]', creds.pw);
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard**', { timeout: 15000 });

// 1a: slider library lists the slider with used-on count
await page.goto(`${BASE}/admin/sites/${SITE}/sliders`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1200);
const body1 = await page.textContent('body');
check('library lists S4 Verify Slider', body1.includes('S4 Verify Slider'));
check('used-on count shown', /1 page(?!s)/.test(body1) || body1.includes('1 page'));
await page.screenshot({ path: `${SHOTS}/1-library.png`, fullPage: true });

// 1b: page builder exposes slider_ref — the saved block renders its picker/preview
await page.goto(`${BASE}/admin/sites/${SITE}/pages/${PAGEID}/edit`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);
const body2 = await page.textContent('body');
check('page canvas renders the slider_ref block', body2.includes('S4 Verify Slider') || body2.includes('slider'), '');
await page.screenshot({ path: `${SHOTS}/2-page-editor.png`, fullPage: true });

// 3: stale view shows the flagged page with the slider reason + badge
await page.goto(`${BASE}/admin/sites/${SITE}/stale-pages`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);
const body3 = await page.textContent('body');
check('stale view lists S4 Verify with slider reason', body3.includes('S4 Verify') && body3.includes("Slider 'S4 Verify Slider' updated"));
const badge = await page.locator('a[href*="stale-pages"] .badge').first().textContent().catch(() => null);
check('sidebar badge shows count', badge !== null && parseInt(badge) >= 1, `badge=${badge}`);
await page.screenshot({ path: `${SHOTS}/3-stale-view.png`, fullPage: true });

// 6: delete protection dialog lists the referring page (cancel, no force)
await page.goto(`${BASE}/admin/sites/${SITE}/sliders`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1000);
const row = page.locator('tr', { hasText: 'S4 Verify Slider' });
await row.locator('button[title="Delete"]').click();
await page.waitForTimeout(400);
await page.locator('.modal button', { hasText: 'Delete' }).first().click(); // confirm normal delete -> 409 -> force dialog
await page.waitForTimeout(1200);
const dlg = await page.textContent('body');
check('delete-protection dialog lists referring page', dlg.includes('still in use') && dlg.includes('S4 Verify'));
await page.screenshot({ path: `${SHOTS}/4-delete-protection.png` });
// cancel — do NOT force
const cancel = page.locator('.modal button', { hasText: /Cancel|Close/i }).first();
if (await cancel.count()) await cancel.click();

await browser.close();
console.log(`\nSUMMARY: ${results.filter(Boolean).length}/${results.length} passed`);
process.exit(results.every(Boolean) ? 0 : 1);
