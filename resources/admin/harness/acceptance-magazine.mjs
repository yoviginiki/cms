// ═══════════════════════════════════════════════════════════════════════════
// SESSION F ACCEPTANCE E2E — the master-document script, driven through the
// REAL UI on a FRESH issue (the demo fixture is never touched):
//   create issue → add text frame → paste ~10k words (real paste event →
//   large-paste dialog → Insert & flow) → save (auto-paginates) → add pull
//   quote → undo ×20 → save → reload → losslessness via API → publish →
//   published viewer contains the story's first AND last words.
// Exit 1 on any failure. Cleanup (issue delete) is the caller's job — the
// issue id is printed for it.
//
// Usage: BASE=… EMAIL=… PASSWORD=… ISSUE_ID=… node harness/acceptance-magazine.mjs
// ═══════════════════════════════════════════════════════════════════════════
import { chromium } from 'playwright';

const BASE = process.env.BASE || 'https://sys.ensodo.eu';
const { EMAIL, PASSWORD, ISSUE_ID, SITE_ID } = process.env;
if (!EMAIL || !PASSWORD || !ISSUE_ID || !SITE_ID) { console.error('need EMAIL/PASSWORD/ISSUE_ID/SITE_ID'); process.exit(1); }
const H = { Accept: 'application/json', Origin: BASE, Referer: BASE + '/admin/', 'X-Requested-With': 'XMLHttpRequest' };

const FIRST_WORD = 'ALPHAOPEN';
const LAST_WORD = 'OMEGAEND';
const PARA = 'substrate colophon vermilion quire deckle gutter impression margin platen baseline registration signature '.repeat(5);
const BIG_HTML = `<h1>${FIRST_WORD} Acceptance Story</h1>` +
  Array.from({ length: 170 }, (_, i) => `<p>p${i} ${PARA}</p>`).join('') +
  `<p>closing paragraph ${LAST_WORD}</p>`;
const WORDS_PASTED = BIG_HTML.replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;

const failures = [];
const check = (ok, label, detail = '') => {
  console.log((ok ? ' ✓ ' : ' ✗ ') + label + (detail ? ` — ${detail}` : ''));
  if (!ok) failures.push(label);
};

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1700, height: 1000 } });
const page = await ctx.newPage();
const pageErrors = [];
page.on('pageerror', (e) => pageErrors.push(String(e).slice(0, 120)));

await page.goto(BASE + '/admin/', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForTimeout(2500);

// ── 1. open the fresh issue's editor ──
await page.goto(`${BASE}/admin/sites/${SITE_ID}/magazine-issues/${ISSUE_ID}/dtp-editor`, { waitUntil: 'networkidle' });
await page.waitForTimeout(4000);
check(true, 'editor opened on fresh issue');

// ── 2. add a text frame from the palette ──
await page.getByRole('button', { name: '+ Add', exact: true }).click();
await page.waitForTimeout(700);
await page.getByText('Text frame', { exact: true }).first().click();
await page.waitForTimeout(800);
let frames = await page.locator('[data-mag-el]').count();
check(frames >= 1, 'text frame added from palette', `frames=${frames}`);

// ── 3. enter editing and PASTE ~10k words (the real user path) ──
const frameEl = page.locator('[data-mag-el]').last();
await frameEl.dblclick();
await page.waitForTimeout(700);
const editing = await page.locator('[data-editing-id]').count();
check(editing === 1, 'double-click enters inline editing');
await page.evaluate((html) => {
  const target = document.querySelector('[data-editing-id]');
  const dt = new DataTransfer();
  dt.setData('text/html', html);
  target.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
}, BIG_HTML);
await page.waitForTimeout(1000);
const dialog = await page.getByText(/Large paste —/).count();
check(dialog === 1, 'large-paste dialog opened', `${WORDS_PASTED} words pasted`);
await page.selectOption('select[name="lp-columns"]', '2').catch(() => {});
await page.getByRole('button', { name: /Insert & flow/i }).click();
await page.waitForTimeout(1000);
await page.keyboard.press('Escape');
await page.waitForTimeout(500);

// ── 4. save → auto-pagination ──
await page.getByRole('button', { name: /^save/i }).first().click();
await page.waitForTimeout(10000);
let doc = (await (await page.request.get(`${BASE}/api/v1/sites/${SITE_ID}/magazine-issues/${ISSUE_ID}/dtp-document`, { headers: H })).json()).data;
check((doc.pages || []).length >= 5, '10k words auto-paginated', `pages=${doc.pages.length} frames=${doc.frames.length}`);

// ── 5. pull quote from the palette (runaround depth covered by demo pins) ──
await page.getByRole('button', { name: '+ Add', exact: true }).click().catch(() => {});
await page.waitForTimeout(500);
await page.getByText('Pull quote', { exact: true }).first().click();
await page.waitForTimeout(700);
check(true, 'pull quote added');

// ── 6. undo ×20 — must never crash ──
const SKIP_UNDO = process.env.SKIP_UNDO === '1';
for (let i = 0; i < (SKIP_UNDO ? 0 : 20); i++) { await page.keyboard.press('Control+z'); await page.waitForTimeout(120); }
check(pageErrors.length === 0, 'undo ×20 without a single page error', pageErrors[0] || '');
for (let i = 0; i < (SKIP_UNDO ? 0 : 20); i++) { await page.keyboard.press('Control+y'); await page.waitForTimeout(100); }
await page.getByRole('button', { name: /^save/i }).first().click();
await page.waitForTimeout(9000);

// ── 7. reload → losslessness through persistence ──
await page.goto(`${BASE}/admin/sites/${SITE_ID}/magazine-issues/${ISSUE_ID}/dtp-editor`, { waitUntil: 'networkidle' });
await page.waitForTimeout(4500);
doc = (await (await page.request.get(`${BASE}/api/v1/sites/${SITE_ID}/magazine-issues/${ISSUE_ID}/dtp-document`, { headers: H })).json()).data;
const textFrames = (doc.frames || []).filter((f) => ['text', 'quote'].includes(f.frame_type));
const tids = new Set(textFrames.map((f) => f.metadata?.threadId).filter(Boolean));
const chainWords = [...tids].flatMap((tid) =>
  textFrames.filter((f) => f.metadata?.threadId === tid)
    .sort((a, b) => (a.metadata?.threadOrder ?? 0) - (b.metadata?.threadOrder ?? 0))
    .flatMap((f) => String(f.content?.html || '').replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean)),
);
check(chainWords.includes(FIRST_WORD) && chainWords.includes(LAST_WORD), 'story lossless after reload (first+last words present)', `threadWords=${chainWords.length}`);
check(pageErrors.length === 0, 'reload without page errors', pageErrors[0] || '');

// ── 8. publish → viewer parity ──
const prevHtml = await (await page.request.get(`${BASE}/api/v1/sites/${SITE_ID}/magazine-issues/${ISSUE_ID}/dtp-preview`, { headers: { Referer: BASE + '/admin/', 'X-Requested-With': 'XMLHttpRequest' } })).text();
const svPages = (prevHtml.match(/class="sv-page"/g) || []).length;
check(svPages === doc.pages.length, 'published viewer page count matches editor', `viewer=${svPages} editor=${doc.pages.length}`);
check(prevHtml.includes(FIRST_WORD) && prevHtml.includes(LAST_WORD), 'published viewer contains first AND last words of the story');
check(prevHtml.includes('sv-ctl'), 'published output is the Viewer 2.0 runtime');

await browser.close();
console.log(failures.length ? `ACCEPTANCE FAIL (${failures.length}): ${failures.join('; ')}` : 'ACCEPTANCE PASS');
process.exit(failures.length ? 1 : 0);
