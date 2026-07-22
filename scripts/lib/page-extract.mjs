/**
 * Shared in-page extractor for the deterministic URL/HTML importers.
 *
 * PAGE_EXTRACTOR runs inside the browser (page.evaluate) and reads the REAL
 * DOM + on-screen geometry into a Stillopress page manifest
 * ({page_title, hostname, blocks[]}). Extracted verbatim from import-url.mjs
 * so the Page Wizard and the Site Wizard emit identical manifests.
 *
 * SITE_SIGNALS_EXTRACTOR is the Site Wizard's extra pass: the navigation
 * links and same-origin anchors (which PAGE_EXTRACTOR deliberately skips),
 * plus deterministic computed-style signals the theme mapper turns into a
 * token profile — no AI involved.
 */

export const PAGE_EXTRACTOR = () => {
  const MAX_BLOCKS = 40;
  const blocks = [];
  const consumed = new WeakSet();
  let heroDone = false;

  const rect = (el) => el.getBoundingClientRect();
  const styl = (el) => getComputedStyle(el);
  const clean = (t, max) => (t || '').replace(/\s+/g, ' ').trim().slice(0, max || 100000);
  const txt = (el) => clean(el.innerText || el.textContent || '', 5000);
  const absUrl = (u) => { try { const a = new URL(u, location.href); return /^https?:/.test(a.protocol) ? a.href : null; } catch { return null; } };

  const visible = (el) => {
    if (!el || el.nodeType !== 1) return false;
    const r = rect(el), s = styl(el);
    return r.width > 1 && r.height > 1 && s.display !== 'none' && s.visibility !== 'hidden' && parseFloat(s.opacity) !== 0;
  };
  const skip = (el) => {
    if (el.closest('nav, footer, [role=navigation], [aria-hidden="true"]')) return true;
    const s = styl(el);
    return s.position === 'fixed' || s.position === 'sticky'; // cookie bars / sticky nav
  };
  const consume = (el) => { consumed.add(el); el.querySelectorAll('*').forEach((n) => consumed.add(n)); };

  const firstImg = (el) => {
    const img = Array.from(el.querySelectorAll('img')).find((i) => visible(i) && (i.naturalWidth > 80 || rect(i).width > 80));
    if (img) { const u = absUrl(img.currentSrc || img.src); if (u) return u; }
    return null;
  };
  const firstHeading = (el) => {
    let h = Array.from(el.querySelectorAll('h1,h2,h3,h4,h5,h6')).find((h) => visible(h) && txt(h));
    if (!h) h = Array.from(el.querySelectorAll('[class*="title"],[class*="heading"]')).find((x) => visible(x) && txt(x) && txt(x).length < 120);
    return h ? clean(txt(h), 150) : '';
  };
  const firstPara = (el) => {
    const p = Array.from(el.querySelectorAll('p,li')).find((p) => visible(p) && txt(p).length > 20);
    return p ? clean(txt(p), 600) : '';
  };
  const firstCta = (el) => {
    const a = Array.from(el.querySelectorAll('a,button')).find((a) => visible(a) && txt(a).length > 1 && txt(a).length < 40);
    if (!a) return null;
    const href = a.tagName === 'A' ? absUrl(a.getAttribute('href')) : null;
    return { text: clean(txt(a), 60), url: href };
  };

  // A CARD GRID: 2–4 side-by-side cells of similar, card-sized height. Kept
  // strict so big image+text page sections are NOT swallowed as one columns
  // block — those get walked into for richer, cleaner blocks.
  const isRow = (el) => {
    const kids = Array.from(el.children).filter(visible);
    if (kids.length < 2 || kids.length > 4) return false;
    const s = styl(el);
    const flexRow = s.display.includes('flex') && !s.flexDirection.startsWith('column');
    const grid = s.display.includes('grid') && s.gridTemplateColumns.split(' ').filter(Boolean).length >= 2;
    const tops = kids.map((k) => Math.round(rect(k).top));
    const sameRow = Math.max(...tops) - Math.min(...tops) < 60;
    if (!((flexRow || grid) && sameRow)) {
      const lefts = kids.map((k) => Math.round(rect(k).left));
      if (!(sameRow && Math.max(...lefts) - Math.min(...lefts) > 120)) return false;
    }
    const heights = kids.map((k) => rect(k).height);
    const maxH = Math.max(...heights), minH = Math.min(...heights);
    const cardLike = maxH < 640 && minH / maxH > 0.5; // similar, not-too-tall = real cards
    const enough = kids.filter((k) => firstHeading(k) || firstPara(k) || firstImg(k)).length >= 2;
    return cardLike && enough;
  };
  const cellOf = (el) => {
    const c = {};
    const h = firstHeading(el); if (h) c.heading = h;
    const b = firstPara(el); if (b) c.body = b;
    const im = firstImg(el); if (im) c.image = im;
    return (c.heading || c.body || c.image) ? c : null;
  };

  const emitHero = (el) => {
    const block = { kind: 'hero', title: clean(txt(el), 200) };
    let sib = el.nextElementSibling;
    while (sib && !visible(sib)) sib = sib.nextElementSibling;
    if (sib && (sib.tagName === 'P' || sib.matches('[class*="sub"],[class*="lead"]'))) {
      const s = clean(txt(sib), 300); if (s) { block.subtitle = s; consume(sib); }
    }
    const cta = firstCta(el.parentElement || el);
    if (cta) { block.cta_text = cta.text; if (cta.url) block.cta_url = cta.url; }
    consume(el);
    return block;
  };

  const walk = (el, depth) => {
    if (blocks.length >= MAX_BLOCKS) return;
    if (!el || consumed.has(el) || !visible(el) || skip(el) || depth > 30) return;
    const tag = el.tagName.toLowerCase();

    if (isRow(el)) {
      const cells = Array.from(el.children).filter(visible).map(cellOf).filter(Boolean).slice(0, 3);
      if (cells.length >= 2) { blocks.push({ kind: 'columns', columns: cells }); consume(el); return; }
    }

    if (/^h[1-6]$/.test(tag)) {
      const t = clean(txt(el), 200);
      if (t) {
        if (!heroDone && (tag === 'h1' || rect(el).top < 900)) { blocks.push(emitHero(el)); heroDone = true; }
        else blocks.push({ kind: 'heading', text: t, level: tag });
      }
      consume(el); return;
    }
    if (tag === 'p') {
      const t = clean(txt(el), 1500);
      if (t.length > 20) blocks.push({ kind: 'text', body: t });
      consume(el); return;
    }
    if (tag === 'img') {
      if (el.naturalWidth > 120 || rect(el).width > 120) {
        const u = absUrl(el.currentSrc || el.src);
        if (u) blocks.push({ kind: 'image', url: u, alt: clean(el.alt, 200) });
      }
      consume(el); return;
    }
    for (const child of Array.from(el.children)) walk(child, depth + 1);
  };

  // Explicit hero: the most prominent heading near the top of the page.
  const heroEl = (() => {
    const cands = Array.from(document.querySelectorAll('h1,h2')).filter((h) => visible(h) && !skip(h) && txt(h) && rect(h).top < 1200);
    if (!cands.length) return null;
    cands.sort((a, b) => parseFloat(styl(b).fontSize) - parseFloat(styl(a).fontSize));
    return cands[0];
  })();
  if (heroEl) { blocks.push(emitHero(heroEl)); heroDone = true; }

  const root = document.querySelector('main') || document.body;
  walk(root, 0);

  // Merge runs of ≥3 consecutive images into a gallery.
  const merged = [];
  for (let i = 0; i < blocks.length; i++) {
    if (blocks[i].kind === 'image') {
      let j = i; const imgs = [];
      while (j < blocks.length && blocks[j].kind === 'image') { imgs.push(blocks[j].url); j++; }
      if (imgs.length >= 3) { merged.push({ kind: 'gallery', images: imgs }); i = j - 1; continue; }
    }
    merged.push(blocks[i]);
  }

  return {
    page_title: clean(document.title, 120) || location.hostname,
    hostname: location.hostname,
    blocks: merged.slice(0, MAX_BLOCKS),
  };
};

export const SITE_SIGNALS_EXTRACTOR = () => {
  const clean = (t, max) => (t || '').replace(/\s+/g, ' ').trim().slice(0, max || 200);
  const rect = (el) => el.getBoundingClientRect();
  const styl = (el) => getComputedStyle(el);
  const visible = (el) => {
    if (!el || el.nodeType !== 1) return false;
    const r = rect(el), s = styl(el);
    return r.width > 1 && r.height > 1 && s.display !== 'none' && s.visibility !== 'hidden';
  };
  const abs = (u) => { try { const a = new URL(u, location.href); return /^https?:/.test(a.protocol) ? a.href : null; } catch { return null; } };

  // ── Navigation: exactly the region PAGE_EXTRACTOR skips ──
  const navRoots = Array.from(document.querySelectorAll('nav, [role=navigation], header'));
  const nav = [];
  const seenNav = new Set();
  for (const root of navRoots) {
    for (const a of Array.from(root.querySelectorAll('a[href]'))) {
      const href = abs(a.getAttribute('href'));
      const label = clean(a.innerText || a.textContent, 60);
      if (!href || !label || label.length > 40) continue;
      if (seenNav.has(href)) continue;
      seenNav.add(href);
      nav.push({ label, href });
      if (nav.length >= 12) break;
    }
    if (nav.length >= 12) break;
  }

  // ── Same-origin links anywhere on the page (crawl frontier) ──
  const links = [];
  const seenLinks = new Set();
  for (const a of Array.from(document.querySelectorAll('a[href]'))) {
    const href = abs(a.getAttribute('href'));
    if (!href) continue;
    try {
      const u = new URL(href);
      if (u.origin !== location.origin) continue;
      const key = u.pathname;
      if (seenLinks.has(key)) continue;
      seenLinks.add(key);
      links.push(href);
      if (links.length >= 80) break;
    } catch { /* ignore */ }
  }

  // ── Style signals for the deterministic theme mapper ──
  const body = styl(document.body);
  const h1 = document.querySelector('h1') || document.querySelector('h2');
  const h2 = document.querySelector('h2') || h1;
  const link = Array.from(document.querySelectorAll('a[href]')).find((a) => visible(a));
  const buttons = Array.from(document.querySelectorAll('button, a[class*="btn"], a[class*="button"], [class*="cta"] a, input[type=submit]'))
    .filter(visible).slice(0, 12)
    .map((b) => {
      const s = styl(b);
      return { background: s.backgroundColor, color: s.color, radius: s.borderRadius };
    });

  // Area-weighted background-color histogram over large visible elements.
  const bgHistogram = {};
  const viewportArea = Math.max(1, document.documentElement.scrollWidth * document.documentElement.scrollHeight);
  for (const el of Array.from(document.querySelectorAll('body, body *')).slice(0, 3000)) {
    if (!visible(el)) continue;
    const r = rect(el);
    const area = r.width * r.height;
    if (area < viewportArea * 0.02) continue; // only big surfaces shape the palette
    const bg = styl(el).backgroundColor;
    if (!bg || bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') continue;
    bgHistogram[bg] = (bgHistogram[bg] || 0) + area;
  }
  const bgColors = Object.entries(bgHistogram)
    .sort((a, b) => b[1] - a[1]).slice(0, 8)
    .map(([color, area]) => ({ color, weight: Math.round(area / viewportArea * 100) / 100 }));

  let shadows = 0, shadowSamples = 0;
  for (const el of Array.from(document.querySelectorAll('div, section, article, a')).slice(0, 400)) {
    if (!visible(el)) continue;
    shadowSamples++;
    const sh = styl(el).boxShadow;
    if (sh && sh !== 'none') shadows++;
  }

  const sections = Array.from(document.querySelectorAll('section, main > div')).filter(visible).slice(0, 20);
  const paddings = sections.map((s) => parseFloat(styl(s).paddingTop) + parseFloat(styl(s).paddingBottom)).filter((n) => !isNaN(n));
  const sectionPadding = paddings.length ? paddings.reduce((a, b) => a + b, 0) / paddings.length : 0;

  const themeColorMeta = document.querySelector('meta[name="theme-color"]');

  return {
    nav,
    links,
    style: {
      title: clean(document.title, 120),
      body: { fontFamily: body.fontFamily, fontSize: body.fontSize, color: body.color, background: styl(document.documentElement).backgroundColor === 'rgba(0, 0, 0, 0)' ? body.backgroundColor : styl(document.documentElement).backgroundColor },
      h1: h1 ? { fontFamily: styl(h1).fontFamily, fontWeight: styl(h1).fontWeight, fontSize: styl(h1).fontSize, color: styl(h1).color } : null,
      h2: h2 ? { fontFamily: styl(h2).fontFamily, fontWeight: styl(h2).fontWeight, fontSize: styl(h2).fontSize } : null,
      link_color: link ? styl(link).color : null,
      buttons,
      background_histogram: bgColors,
      shadow_ratio: shadowSamples ? Math.round(shadows / shadowSamples * 100) / 100 : 0,
      section_padding: Math.round(sectionPadding),
      theme_color_meta: themeColorMeta ? themeColorMeta.getAttribute('content') : null,
    },
  };
};

export async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let y = 0;
      const t = setInterval(() => {
        window.scrollBy(0, 600); y += 600;
        if (y >= document.documentElement.scrollHeight - window.innerHeight || y > 30000) { clearInterval(t); resolve(); }
      }, 80);
    });
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(500);
}
