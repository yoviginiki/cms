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

  // Design fidelity: every block carries the EFFECTIVE background of its
  // section and its own text color, so the compiler can rebuild the page's
  // light/dark section rhythm instead of flattening everything onto the
  // theme default.
  const toHex = (c) => {
    const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/.exec(c || '');
    if (!m) return null;
    if (m[4] !== undefined && parseFloat(m[4]) < 0.5) return null;
    const h = (n) => (+n).toString(16).padStart(2, '0');
    return '#' + h(m[1]) + h(m[2]) + h(m[3]);
  };
  const effBg = (el) => {
    let n = el;
    while (n && n.nodeType === 1) {
      const b = toHex(styl(n).backgroundColor);
      if (b) return b;
      n = n.parentElement;
    }
    return toHex(styl(document.body).backgroundColor);
  };
  const deco = (el, block) => {
    const b = effBg(el);
    const f = toHex(styl(el).color);
    if (b) block._bg = b;
    if (f) block._fg = f;
    const anim = animOf(el);
    if (anim) { block._anim = anim.name; if (anim.delay) block._anim_delay = anim.delay; }
    return block;
  };

  // Entrance animation the source assigned to this element (or a wrapper):
  // Elementor data-settings _animation, animate.css classes, or AOS attrs —
  // mapped onto the CMS's own entrance vocabulary.
  const ANIM_MAP = {
    fadein: 'fade', fadeinup: 'slide-up', fadeindown: 'slide-down',
    fadeinleft: 'slide-left', fadeinright: 'slide-right',
    slideinup: 'slide-up', slideindown: 'slide-down', slideinleft: 'slide-left', slideinright: 'slide-right',
    zoomin: 'zoom', zoominup: 'zoom', bouncein: 'scale-in', pulse: 'scale-in',
    'fade-up': 'slide-up', 'fade-down': 'slide-down', 'fade-left': 'slide-left',
    'fade-right': 'slide-right', 'zoom-in': 'zoom', fade: 'fade',
  };
  // Hover/transition effect the source gives this element (or a wrapper):
  // named effect classes, or a real transform/shadow transition on the card.
  const hoverOf = (el) => {
    let n = el, depth = 0;
    while (n && n.nodeType === 1 && depth <= 4) {
      const cls = String(n.className || '').toLowerCase();
      if (/shiny|glass|zoom-hover|hover-zoom/.test(cls)) return 'scale';
      if (/hover-lift|lift-hover/.test(cls)) return 'lift';
      const s = styl(n);
      const props = s.transitionProperty || '';
      const dur = parseFloat(s.transitionDuration) || 0;
      if (dur >= 0.15 && /(transform|box-shadow|\ball\b)/.test(props)) return 'lift-scale';
      n = n.parentElement; depth++;
    }
    return null;
  };

  const animOf = (el) => {
    let n = el, depth = 0;
    while (n && n.nodeType === 1 && depth <= 6) {
      const ds = n.getAttribute && n.getAttribute('data-settings');
      if (ds) {
        const m = /"_animation(?:_mobile|_tablet)?"\s*:\s*"([a-zA-Z-]+)"/.exec(ds);
        if (m && ANIM_MAP[m[1].toLowerCase()]) {
          const d = /"_animation_delay"\s*:\s*"?(\d+)"?/.exec(ds);
          return { name: ANIM_MAP[m[1].toLowerCase()], delay: d ? Math.min(3000, +d[1]) : 0 };
        }
      }
      const aos = n.getAttribute && n.getAttribute('data-aos');
      if (aos && ANIM_MAP[aos.toLowerCase()]) return { name: ANIM_MAP[aos.toLowerCase()], delay: 0 };
      for (const cls of (n.classList ? Array.from(n.classList) : [])) {
        const key = cls.toLowerCase();
        if (key !== 'fade' && ANIM_MAP[key]) return { name: ANIM_MAP[key], delay: 0 };
      }
      n = n.parentElement; depth++;
    }
    return null;
  };

  const firstImg = (el) => {
    const img = Array.from(el.querySelectorAll('img')).find((i) => visible(i) && (i.naturalWidth > 80 || rect(i).width > 80));
    if (img) { const u = absUrl(img.currentSrc || img.src); if (u) return u; }
    return null;
  };

  // Effective CSS background-image (heroes/banners style them on a wrapper).
  const bgImageOf = (el, maxUp) => {
    let n = el, depth = 0;
    while (n && n.nodeType === 1 && depth <= (maxUp ?? 6)) {
      const m = /url\(["']?([^"')]+)["']?\)/.exec(styl(n).backgroundImage || '');
      if (m && !/^data:/i.test(m[1])) { const u = absUrl(m[1]); if (u) return u; }
      n = n.parentElement; depth++;
    }
    return null;
  };

  // The full-width band an element belongs to (for hero backgrounds/CTAs).
  const sectionOf = (el) => {
    let n = el, best = null;
    while (n && n.nodeType === 1 && n !== document.body) {
      const r = rect(n);
      if (r.height >= 240 && r.width >= innerWidth * 0.6) { best = n; if (r.height >= 340) break; }
      n = n.parentElement;
    }
    return best || el.parentElement || el;
  };

  // A link/button STYLED as a button: explicit class, or padded with its own fill.
  const looksButton = (el) => {
    if (!el || !/^(a|button)$/i.test(el.tagName)) return false;
    const t = txt(el);
    if (!t || t.length < 2 || t.length > 40 || el.querySelector('img')) return false;
    if (/(^|[\s_-])(btn|button)([\s_-]|$)/i.test(String(el.className || ''))) return true;
    const s = styl(el);
    return toHex(s.backgroundColor) !== null && parseFloat(s.paddingLeft) >= 10 && parseFloat(s.paddingTop) >= 6;
  };

  // A strip of ≥4 small same-band images (brand/partner logo rows).
  const logoRow = (el) => {
    if (rect(el).height > 400) return null;
    const imgs = Array.from(el.querySelectorAll('img')).filter((i) => visible(i));
    if (imgs.length < 4) return null;
    const small = imgs.filter((i) => { const r = rect(i); return r.height > 12 && r.height <= 130 && r.width <= 420; });
    if (small.length < 4 || small.length < imgs.length * 0.8) return null;
    const tops = small.map((i) => Math.round(rect(i).top));
    if (Math.max(...tops) - Math.min(...tops) > 200) return null;
    const urls = [...new Set(small.map((i) => absUrl(i.currentSrc || i.src)).filter(Boolean))];
    return urls.length >= 4 ? urls.slice(0, 12) : null;
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
    if (!c.heading && !c.body && !c.image) {
      // Counters/stat tiles: no h/p markup — first text line is the figure,
      // the rest the label.
      const lines = (el.innerText || '').split('\n').map((s) => s.trim()).filter(Boolean);
      if (lines.length && lines.join(' ').length <= 400) {
        c.heading = clean(lines[0], 80);
        const rest = clean(lines.slice(1).join(' '), 300);
        if (rest) c.body = rest;
      }
    }
    return (c.heading || c.body || c.image) ? c : null;
  };

  const emitHero = (el) => {
    const block = deco(el, { kind: 'hero', title: clean(txt(el), 200) });
    const sect = sectionOf(el);

    // Background: effective CSS background-image of the hero band; else ANY
    // element overlapping the band that paints one (builders often put the
    // photo on an absolutely-positioned layer OUTSIDE the heading's ancestry);
    // else a large <img> covering most of the band.
    let bgi = bgImageOf(el) || bgImageOf(sect, 2);
    if (!bgi) {
      const sr = rect(sect);
      let bestArea = 0;
      for (const cand of document.querySelectorAll('div,section')) {
        const r = rect(cand);
        if (r.height < sr.height * 0.5 || r.width < sr.width * 0.5) continue;
        const overlap = Math.min(r.bottom, sr.bottom) - Math.max(r.top, sr.top);
        if (overlap < sr.height * 0.5) continue;
        const m = /url\(["']?([^"')]+)["']?\)/.exec(styl(cand).backgroundImage || '');
        if (m && !/^data:/i.test(m[1])) {
          const area = r.width * r.height;
          if (area > bestArea) { bestArea = area; bgi = absUrl(m[1]); }
        }
      }
    }
    if (!bgi) {
      const sr = rect(sect);
      const big = Array.from(sect.querySelectorAll('img')).find((i) =>
        visible(i) && rect(i).width >= sr.width * 0.5 && rect(i).height >= sr.height * 0.5);
      if (big) bgi = absUrl(big.currentSrc || big.src);
    }
    if (bgi) block.image = bgi;

    let sib = el.nextElementSibling;
    while (sib && !visible(sib)) sib = sib.nextElementSibling;
    if (sib && (sib.tagName === 'P' || sib.matches('[class*="sub"],[class*="lead"]'))) {
      const s = clean(txt(sib), 300); if (s) { block.subtitle = s; consume(sib); }
    }

    // CTA: a button-styled link anywhere in the hero band beats the nearest anchor.
    const btn = Array.from(sect.querySelectorAll('a,button')).find((a) => visible(a) && !skip(a) && looksButton(a));
    if (btn) {
      block.cta_text = clean(txt(btn), 60);
      const href = btn.tagName === 'A' ? absUrl(btn.getAttribute('href')) : null;
      if (href) block.cta_url = href;
      consume(btn);
    } else {
      const cta = firstCta(el.parentElement || el);
      if (cta) { block.cta_text = cta.text; if (cta.url) block.cta_url = cta.url; }
    }
    consume(el);
    return block;
  };

  const walk = (el, depth) => {
    if (blocks.length >= MAX_BLOCKS) return;
    if (!el || consumed.has(el) || !visible(el) || skip(el) || depth > 30) return;
    const tag = el.tagName.toLowerCase();

    const logos = logoRow(el);
    if (logos) { blocks.push(deco(el, { kind: 'gallery', images: logos })); consume(el); return; }

    if (isRow(el)) {
      const kids = Array.from(el.children).filter(visible);
      const cells = kids.map(cellOf).filter(Boolean).slice(0, 3);
      if (cells.length >= 2) {
        const b = deco(el, { kind: 'columns', columns: cells });
        const hv = kids.length ? hoverOf(kids[0]) : null;
        if (hv) b._hover = hv;
        blocks.push(b); consume(el); return;
      }
    }

    if (looksButton(el)) {
      // Deco from the PARENT: the button's own fill is widget styling — read
      // as section background it would paint the whole section that color.
      const b = deco(el.parentElement || el, { kind: 'button', text: clean(txt(el), 60) });
      const href = el.tagName === 'A' ? absUrl(el.getAttribute('href')) : null;
      if (href) b.url = href;
      blocks.push(b);
      consume(el); return;
    }

    if (/^h[1-6]$/.test(tag)) {
      const t = clean(txt(el), 200);
      if (t) {
        if (!heroDone && (tag === 'h1' || rect(el).top < 900)) { blocks.push(emitHero(el)); heroDone = true; }
        else blocks.push(deco(el, { kind: 'heading', text: t, level: tag }));
      }
      consume(el); return;
    }
    if (tag === 'p') {
      const t = clean(txt(el), 1500);
      if (t.length > 20) blocks.push(deco(el, { kind: 'text', body: t }));
      consume(el); return;
    }
    if (tag === 'img') {
      if (el.naturalWidth > 120 || rect(el).width > 120) {
        const u = absUrl(el.currentSrc || el.src);
        if (u) {
          const b = deco(el, { kind: 'image', url: u, alt: clean(el.alt, 200) });
          const hv = hoverOf(el); if (hv) b._hover = hv;
          blocks.push(b);
        }
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
      if (imgs.length >= 3) {
        const g = { kind: 'gallery', images: imgs };
        if (blocks[i]._bg) g._bg = blocks[i]._bg;
        merged.push(g); i = j - 1; continue;
      }
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
