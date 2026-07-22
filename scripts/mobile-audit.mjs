/**
 * Mobile layout audit for the migration pipeline: loads a URL at a phone
 * viewport and reports what a content diff can never see — horizontal
 * overflow, elements wider than the screen, and squeezed grid columns.
 * Usage: node scripts/mobile-audit.mjs <url> [widthxheight]
 * Prints one JSON object to stdout.
 */
import { chromium } from 'playwright';

const url = process.argv[2];
const dims = (process.argv[3] || '390x844').split('x').map((n) => parseInt(n, 10));
const [width, height] = [dims[0] || 390, dims[1] || 844];

if (!url || !/^https?:\/\//i.test(url)) {
  process.stderr.write('ERROR: a http(s) URL is required\n');
  process.exit(1);
}

const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
  await page.waitForTimeout(800);

  const report = await page.evaluate(() => {
    const vw = window.innerWidth;
    const doc = document.documentElement;

    const describe = (el) => {
      const id = el.id ? `#${el.id}` : '';
      const cls = el.className && typeof el.className === 'string'
        ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.')
        : '';
      return `${el.tagName.toLowerCase()}${id}${cls}`;
    };

    // elements that extend past the viewport (ignore html/body themselves)
    const offenders = [];
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect();
      if (r.width > vw + 2 || r.right > vw + 8) {
        // skip elements whose parent is already reported (report roots only)
        const parent = el.parentElement;
        if (parent) {
          const pr = parent.getBoundingClientRect();
          if (pr.width > vw + 2 || pr.right > vw + 8) continue;
        }
        offenders.push({ el: describe(el), width: Math.round(r.width), right: Math.round(r.right) });
        if (offenders.length >= 10) break;
      }
    }

    // grid containers whose columns are squeezed unusably narrow
    const squeezed = [];
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el);
      if (cs.display !== 'grid') continue;
      const cols = cs.gridTemplateColumns.split(' ').filter((c) => c !== '0px');
      if (cols.length >= 2) {
        const colWidth = el.getBoundingClientRect().width / cols.length;
        if (colWidth < 140 && el.children.length >= cols.length) {
          squeezed.push({ el: describe(el), columns: cols.length, colWidth: Math.round(colWidth) });
          if (squeezed.length >= 6) break;
        }
      }
    }

    // background seams: a section with zero bottom padding whose last visible
    // child ends short of the section edge shows a stripe of raw background
    // (e.g. a decorative divider image with leftover module margin)
    const seams = [];
    for (const sec of document.querySelectorAll('.section-block')) {
      const cs = getComputedStyle(sec);
      if (parseFloat(cs.paddingBottom) > 2) continue;
      if (cs.backgroundColor === 'rgba(0, 0, 0, 0)' && !cs.backgroundImage.includes('url')) continue;
      const sr = sec.getBoundingClientRect();
      if (sr.height < 40) continue;
      // leaf content only — stretched wrapper divs reach the section edge
      // and would mask the seam
      let lastBottom = 0;
      for (const child of sec.querySelectorAll('img, p, h1, h2, h3, h4, ul, ol, a, blockquote, span')) {
        const r = child.getBoundingClientRect();
        if (r.height > 0 && r.bottom > lastBottom) lastBottom = r.bottom;
      }
      const gap = Math.round(sr.bottom - lastBottom);
      if (lastBottom > 0 && gap > 6) {
        seams.push({ el: describe(sec), gap });
        if (seams.length >= 5) break;
      }
    }

    // text blocks flush against the screen edge (no breathing room)
    let edgeFlushText = 0;
    for (const el of document.querySelectorAll('p, h1, h2, h3, li')) {
      const r = el.getBoundingClientRect();
      if (r.width > 100 && (r.left <= 2 || r.right >= vw - 2) && el.textContent.trim().length > 20) {
        edgeFlushText++;
      }
    }

    return {
      viewport: vw,
      scrollWidth: doc.scrollWidth,
      horizontalOverflow: doc.scrollWidth > vw + 2,
      offenders,
      squeezedGrids: squeezed,
      sectionSeams: seams,
      edgeFlushTextBlocks: edgeFlushText,
    };
  });

  report.url = url;
  process.stdout.write(JSON.stringify(report));
} catch (e) {
  process.stdout.write(JSON.stringify({ url, error: String(e.message || e) }));
} finally {
  await browser.close();
}
