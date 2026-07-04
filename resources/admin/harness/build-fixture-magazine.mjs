// ═══════════════════════════════════════════════════════════════════════════
// Fixture magazine builder (Session C Ph4 fixture "a" / user demo).
// Seeds a DRAFT magazine issue with a professional feature layout, then
// drives the REAL editor's Save so the flow engine paginates it:
//   hero image • headline + standfirst • drop-cap 2-column body with
//   headings/quotes • captioned inline figure • pull quote with runaround
//
// Usage:
//   BASE=https://sys.ensodo.eu SITE_ID=... ISSUE_ID=... EMAIL=... PASSWORD=... \
//     node harness/build-fixture-magazine.mjs
// The issue must already exist (draft). Requires playwright + network access.
// ═══════════════════════════════════════════════════════════════════════════
import { chromium } from 'playwright';

const BASE = process.env.BASE || 'https://sys.ensodo.eu';
const SID = process.env.SITE_ID;
const IID = process.env.ISSUE_ID;
const EMAIL = process.env.EMAIL;
const PASSWORD = process.env.PASSWORD;
if (!SID || !IID || !EMAIL || !PASSWORD) {
  console.error('Missing SITE_ID / ISSUE_ID / EMAIL / PASSWORD');
  process.exit(1);
}
const H = { Accept: 'application/json', Origin: BASE, Referer: BASE + '/admin/', 'X-Requested-With': 'XMLHttpRequest' };

// ── article ──────────────────────────────────────────────────────────────
const bank = ['substrate', 'vermilion', 'signature', 'baseline', 'gutter', 'colophon', 'margin', 'quire', 'platen', 'deckle', 'registration', 'impression'];
const sent = (i, n = 14) => {
  const w = [];
  for (let j = 0; j < n; j++) w.push(bank[(i * 29 + j * 7) % bank.length]);
  const s = w.join(' ');
  return s.charAt(0).toUpperCase() + s.slice(1) + '.';
};
let article = '';
for (let i = 1; i <= 46; i++) {
  if (i % 9 === 0) article += `<h2>The ${bank[i % bank.length]} principle</h2>`;
  else if (i % 13 === 0) article += `<blockquote>The grid is the drum the page keeps time on — pressroom saying, c. ${1900 + i}.</blockquote>`;
  else article += `<p>${sent(i)} ${sent(i + 100)} ${sent(i + 200)} ${sent(i + 300)}</p>`;
  if (i === 6) {
    article += '<figure style="float:left;width:42%;margin:0 12px 8px 0;">'
      + '<img src="https://picsum.photos/seed/stillopress/900/600" alt="Wood type drying" style="width:100%;height:auto;display:block;">'
      + '<figcaption style="font-size:10px;opacity:0.7;margin-top:4px;">Wood type drying in the north window of the workshop.</figcaption></figure>';
  }
}

const typo = (over = {}) => ({
  fontFamily: 'Inter', fontSize: 14, fontWeight: 400, fontStyle: 'normal', lineHeight: 1.5,
  letterSpacing: 0, wordSpacing: 0, textAlign: 'justify', textAlignLast: 'auto', textTransform: 'none',
  textIndent: 0, textColor: '#1a1a1a', paragraphSpacingBefore: 0, paragraphSpacingAfter: 12,
  hyphenation: true, hangingPunctuation: false, opticalMarginAlignment: false, maxCharsPerLine: null,
  dropCap: { enabled: false, lines: 3, font: null, color: null },
  openType: { ligatures: true, oldstyleNums: false, tabularNums: false, smallCaps: false, swashes: false },
  baselineShift: 0, kerning: 'metrics', orphans: 2, widows: 2, paragraphStyleId: null, characterStyleId: null,
  ...over,
});

const uid = () => crypto.randomUUID();
const pageId = uid(); const spreadId = uid();
const frame = (over) => ({
  id: uid(), page_id: pageId, spread_id: spreadId, rotation: 0, visible: true, locked: false,
  style: {}, metadata: {}, ...over,
});

const payload = {
  spreads: [{ id: spreadId, spread_index: 0, name: 'Opener' }],
  pages: [{ id: pageId, spread_id: spreadId, page_index: 0, side: 'single', width: 595, height: 842, margins: { top: 36, right: 36, bottom: 36, left: 36 }, bleed: { top: 9, right: 9, bottom: 9, left: 9 }, background: { color: '#ffffff' }, master_page_id: null }],
  layers: [],
  frames: [
    // hero image, top 40%
    frame({ frame_type: 'image', name: 'Hero', x: 0, y: 0, width: 595, height: 330, z_index: 1,
      content: { src: 'https://picsum.photos/seed/ensodo-hero/1400/800', alt: 'Press hall at dawn', fitMode: 'fill', focalPoint: { x: 0.5, y: 0.35 }, opacity: 100 } }),
    // headline overlapping hero bottom
    frame({ frame_type: 'text', name: 'Headline', x: 36, y: 270, width: 420, height: 96, z_index: 3,
      content: { html: '<h1>The Substrate Remembers</h1>', textInset: { top: 12, right: 16, bottom: 8, left: 16 } },
      metadata: { _typography: typo({ fontFamily: 'Playfair Display', fontSize: 34, fontWeight: 700, lineHeight: 1.05, textAlign: 'left', hyphenation: false }) },
      style: { fill: { color: '#ffffff', opacity: 1, gradient: null } } }),
    // standfirst
    frame({ frame_type: 'text', name: 'Standfirst', x: 36, y: 370, width: 523, height: 54, z_index: 2,
      content: { html: '<p>Inside the workshop where the grid is a drum, vermilion is a schedule, and a long essay lands on the page in one motion.</p>', textInset: { top: 4, right: 8, bottom: 4, left: 0 } },
      metadata: { _typography: typo({ fontSize: 15, textColor: '#4c463c', textAlign: 'left', hyphenation: false }) } }),
    // pull quote — engine runaround exclusion (wrap mode)
    frame({ frame_type: 'quote', name: 'Pull quote', x: 180, y: 560, width: 240, height: 110, z_index: 4,
      content: { html: '<p>We do not fill pages. We tune them.</p>', textInset: { top: 12, right: 12, bottom: 12, left: 12 } },
      metadata: {
        _typography: typo({ fontSize: 18, fontWeight: 600, lineHeight: 1.2, textAlign: 'left', hyphenation: false, textColor: '#E63B2E' }),
        _textWrap: { type: 'bounding-box', offset: { top: 14, right: 14, bottom: 14, left: 14 }, side: 'both', customPath: null, invert: false },
      } }),
    // body — 2 columns, drop cap, flows below the standfirst
    frame({ frame_type: 'text', name: 'Body', x: 36, y: 436, width: 523, height: 370, z_index: 2,
      content: { html: article, columnsInFrame: 2, columnGap: 16, textInset: { top: 8, right: 8, bottom: 8, left: 8 } },
      metadata: { _typography: typo({ dropCap: { enabled: true, lines: 3, font: 'Playfair Display', color: '#E63B2E' } }) } }),
  ],
  asset_references: [],
  meta: { issueSettings: { layoutMode: 'book', coverMode: 'standalone', readingDirection: 'ltr' }, styles: [], masterPages: [] },
};

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1600, height: 1000 } });
const page = await ctx.newPage();
await page.goto(BASE + '/admin/', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForTimeout(2500);
const xsrf = decodeURIComponent((await ctx.cookies(BASE)).find((c) => c.name === 'XSRF-TOKEN')?.value || '');
const put = await page.request.put(`${BASE}/api/v1/sites/${SID}/magazine-issues/${IID}/dtp-document`, {
  data: payload, headers: { ...H, 'X-XSRF-TOKEN': xsrf },
});
console.log('SEED:', put.status());
if (put.status() >= 300) { console.error(await put.text()); process.exit(1); }

// open the editor and Save — the flow engine paginates the story
await page.goto(`${BASE}/admin/sites/${SID}/magazine-issues/${IID}/dtp-editor`, { waitUntil: 'networkidle' });
await page.waitForTimeout(4500);
await page.getByRole('button', { name: /^save/i }).first().click();
await page.waitForTimeout(9000);
const doc = (await (await page.request.get(`${BASE}/api/v1/sites/${SID}/magazine-issues/${IID}/dtp-document`, { headers: H })).json()).data;
console.log('RESULT: pages=', (doc.pages || []).length, 'frames=', (doc.frames || []).length);
console.log('EDITOR:', `${BASE}/admin/sites/${SID}/magazine-issues/${IID}/dtp-editor`);
await browser.close();
