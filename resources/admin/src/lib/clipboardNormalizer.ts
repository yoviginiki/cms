// ═══════════════════════════════════════════════════════════════════════════
// Clipboard normalizer (Session D, W1-3 / Session E item 1 seed)
//
// Word, Google Docs and web clipboards arrive as soup: mso-* classes, style
// spans, docs-internal-guid <b> wrappers, comments, nested divs. This module
// reduces any clipboard HTML to the magazine content model:
//   blocks:  p, h1-h6, blockquote, ul/ol/li, figure/figcaption, img, hr
//   inline:  strong, em, u, s, a[href], br, sub, sup
// Everything else is unwrapped (children hoisted) or dropped. Styling never
// survives — typography comes from the frame/styles, not the clipboard.
// ═══════════════════════════════════════════════════════════════════════════

const BLOCK_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'UL', 'OL', 'LI', 'FIGURE', 'FIGCAPTION', 'IMG', 'HR', 'TABLE', 'TR', 'TD', 'TH', 'THEAD', 'TBODY']);
const INLINE_KEEP: Record<string, string> = {
  STRONG: 'strong', B: 'strong', EM: 'em', I: 'em', U: 'u', S: 's', STRIKE: 's',
  A: 'a', BR: 'br', SUB: 'sub', SUP: 'sup',
};
const DROP_ENTIRELY = new Set(['STYLE', 'SCRIPT', 'META', 'LINK', 'TITLE', 'HEAD', 'XML', 'NOSCRIPT', 'IFRAME', 'OBJECT', 'EMBED', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'SVG']);

function isMsoListParagraph(el: Element): boolean {
  const cls = el.getAttribute('class') || '';
  return /MsoListParagraph/i.test(cls);
}

/** effective bold/italic from inline style (Google Docs uses styled spans) */
function styleFlags(el: Element): { bold: boolean; italic: boolean; normal: boolean } {
  const st = (el.getAttribute('style') || '').toLowerCase();
  // (?:^|[;\s]) boundary: 'mso-bidi-font-weight:normal' must NOT count as
  // font-weight — real Word bold is <b style='mso-bidi-font-weight:normal'>
  return {
    bold: /(?:^|[;\s])font-weight\s*:\s*(bold|[7-9]00)/.test(st),
    italic: /(?:^|[;\s])font-style\s*:\s*italic/.test(st),
    normal: /(?:^|[;\s])font-weight\s*:\s*(normal|400)/.test(st),
  };
}

function cleanInline(node: Node, doc: Document, out: Node[]): void {
  if (node.nodeType === Node.TEXT_NODE) {
    const t = (node.textContent || '').replace(/ /g, ' ');
    if (t) out.push(doc.createTextNode(t));
    return;
  }
  if (node.nodeType !== Node.ELEMENT_NODE) return; // comments etc.
  const el = node as Element;
  const tag = el.tagName;
  if (DROP_ENTIRELY.has(tag)) return;

  if (tag === 'IMG') {
    const src = el.getAttribute('src') || '';
    if (/^https?:\/\//i.test(src) || src.startsWith('/')) {
      const img = doc.createElement('img');
      img.setAttribute('src', src);
      img.setAttribute('alt', el.getAttribute('alt') || '');
      out.push(img);
    }
    return;
  }
  if (tag === 'BR') { out.push(doc.createElement('br')); return; }

  // children first
  const kids: Node[] = [];
  el.childNodes.forEach((c) => cleanInline(c, doc, kids));
  if (kids.length === 0 && tag !== 'BR') return;

  let keep = INLINE_KEEP[tag] || null;
  const flags = styleFlags(el);
  // Google Docs: <b style="font-weight:normal"> guid wrapper is NOT bold
  if (keep === 'strong' && flags.normal) keep = null;
  // styled spans → semantic tags
  if (!keep && tag === 'SPAN') {
    if (flags.bold) keep = 'strong';
    else if (flags.italic) keep = 'em';
  }

  if (keep === 'a') {
    const href = el.getAttribute('href') || '';
    if (/^https?:\/\//i.test(href)) {
      const a = doc.createElement('a');
      a.setAttribute('href', href);
      a.setAttribute('rel', 'noopener noreferrer');
      kids.forEach((k) => a.appendChild(k));
      out.push(a);
      return;
    }
    keep = null; // unsafe link → unwrap to its text
  }

  if (keep) {
    const wrapped = doc.createElement(keep);
    kids.forEach((k) => wrapped.appendChild(k));
    // nested italic inside styled-bold spans (GDocs puts both on one span)
    if (tag === 'SPAN' && flags.bold && flags.italic && keep === 'strong') {
      const em = doc.createElement('em');
      while (wrapped.firstChild) em.appendChild(wrapped.firstChild);
      wrapped.appendChild(em);
    }
    out.push(wrapped);
  } else {
    kids.forEach((k) => out.push(k)); // unwrap
  }
}

function pushParagraph(doc: Document, blocks: Element[], inline: Node[]): void {
  // trim leading/trailing whitespace-only nodes
  while (inline.length && !((inline[0].textContent || '').trim()) && (inline[0] as Element).tagName !== 'IMG') inline.shift();
  while (inline.length && !((inline[inline.length - 1].textContent || '').trim()) && (inline[inline.length - 1] as Element).tagName !== 'IMG') inline.pop();
  if (!inline.length) return;
  const p = doc.createElement('p');
  inline.forEach((n) => p.appendChild(n));
  if ((p.textContent || '').trim() || p.querySelector('img')) blocks.push(p);
}

function cleanBlock(el: Element, doc: Document, blocks: Element[], pending: Node[]): void {
  const tag = el.tagName;
  if (DROP_ENTIRELY.has(tag)) return;

  // containers → recurse (div, section, article, docs guid <b>, table soup…)
  const isHeading = /^H[1-6]$/.test(tag);
  const isKnownBlock = BLOCK_TAGS.has(tag) && tag !== 'IMG';

  if (!isKnownBlock) {
    const looksInline = INLINE_KEEP[tag] || tag === 'SPAN' || tag === 'IMG' || tag === 'FONT';
    // Google Docs wraps the WHOLE document in <b id="docs-internal-guid">;
    // an "inline" element containing block children is really a container
    const hasBlockChild = looksInline && tag !== 'IMG'
      ? !!el.querySelector('p,h1,h2,h3,h4,h5,h6,ul,ol,blockquote,div,li,table,figure')
      : false;
    if (looksInline && !hasBlockChild) {
      cleanInline(el, doc, pending); // inline content at block level → pending ¶
      return;
    }
    // generic container: flush pending, recurse into children
    pushParagraph(doc, blocks, pending.splice(0));
    el.childNodes.forEach((c) => {
      if (c.nodeType === Node.TEXT_NODE) cleanInline(c, doc, pending);
      else if (c.nodeType === Node.ELEMENT_NODE) cleanBlock(c as Element, doc, blocks, pending);
    });
    pushParagraph(doc, blocks, pending.splice(0));
    return;
  }

  pushParagraph(doc, blocks, pending.splice(0));

  if (tag === 'HR') { blocks.push(doc.createElement('hr')); return; }

  if (tag === 'UL' || tag === 'OL') {
    const list = doc.createElement(tag.toLowerCase());
    el.querySelectorAll(':scope > li, :scope > * > li').forEach((li) => {
      const inline: Node[] = [];
      li.childNodes.forEach((c) => cleanInline(c, doc, inline));
      if (inline.length) {
        const item = doc.createElement('li');
        inline.forEach((n) => item.appendChild(n));
        list.appendChild(item);
      }
    });
    if (list.children.length) blocks.push(list);
    return;
  }

  if (tag === 'TABLE' || tag === 'THEAD' || tag === 'TBODY' || tag === 'TR' || tag === 'TD' || tag === 'TH') {
    // v1: flatten table cells to paragraphs (real tables are a later track)
    el.childNodes.forEach((c) => {
      if (c.nodeType === Node.ELEMENT_NODE) cleanBlock(c as Element, doc, blocks, pending);
      else cleanInline(c, doc, pending);
    });
    pushParagraph(doc, blocks, pending.splice(0));
    return;
  }

  // real figures keep their img + caption structure (W1-11 supports them)
  if (tag === 'FIGURE') {
    const img = el.querySelector('img[src^="http"], img[src^="https"]');
    const cap = el.querySelector('figcaption');
    if (img) {
      const fig = doc.createElement('figure');
      const im = doc.createElement('img');
      im.setAttribute('src', img.getAttribute('src') || '');
      if (img.getAttribute('alt')) im.setAttribute('alt', img.getAttribute('alt')!);
      fig.appendChild(im);
      const capText = (cap?.textContent || '').trim();
      if (capText) {
        const fc = doc.createElement('figcaption');
        fc.textContent = capText;
        fig.appendChild(fc);
      }
      blocks.push(fig);
      return;
    }
  }

  // p / h1-h6 / blockquote / li / figure / figcaption
  const inline: Node[] = [];
  el.childNodes.forEach((c) => {
    if (c.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has((c as Element).tagName) && (c as Element).tagName !== 'IMG') {
      pushParagraph(doc, blocks, inline.splice(0));
      cleanBlock(c as Element, doc, blocks, pending);
    } else {
      cleanInline(c, doc, inline);
    }
  });
  if (!inline.length) return;

  let outTag = tag.toLowerCase();
  if (tag === 'LI' || tag === 'FIGCAPTION') outTag = 'p'; // stray li/caption
  let msoListKind: 'ol' | 'ul' | null = null;
  if (isMsoListParagraph(el)) {
    // Word fakes lists as paragraphs with a literal "1." / "·" marker span
    // (mso-list:Ignore). Detect the kind from the marker, DROP the marker,
    // and emit a real <li>; runs of them group into <ol>/<ul> afterwards.
    const markerText = (inline.length && inline[0].textContent) || '';
    msoListKind = /^\s*\d+[.)]/.test(markerText) ? 'ol' : 'ul';
    if (/^\s*(\d+[.)]|[·•o§-])/.test(markerText)) inline.shift(); // remove marker node
    outTag = 'li';
  }
  if (isHeading) outTag = tag.toLowerCase();
  const block = doc.createElement(outTag);
  inline.forEach((n) => block.appendChild(n));
  if (msoListKind) block.setAttribute('data-mso-list', msoListKind);
  if ((block.textContent || '').trim() || block.querySelector('img')) blocks.push(block);
}

/** normalize arbitrary clipboard HTML to magazine content HTML */
export function normalizeClipboardHtml(html: string): string {
  // strip comments (Word conditional comments carry whole junk trees)
  const withoutComments = html.replace(/<!--[\s\S]*?-->/g, '');
  const doc = new DOMParser().parseFromString(withoutComments, 'text/html');
  const blocks: Element[] = [];
  const pending: Node[] = [];
  doc.body.childNodes.forEach((c) => {
    if (c.nodeType === Node.TEXT_NODE) cleanInline(c, doc, pending);
    else if (c.nodeType === Node.ELEMENT_NODE) cleanBlock(c as Element, doc, blocks, pending);
  });
  pushParagraph(doc, blocks, pending.splice(0));

  // group consecutive Word list items into real <ol>/<ul>
  const out: Element[] = [];
  let run: Element | null = null;
  for (const b of blocks) {
    const kind = b.getAttribute('data-mso-list');
    if (kind) {
      b.removeAttribute('data-mso-list');
      if (!run || run.tagName.toLowerCase() !== kind) {
        run = doc.createElement(kind);
        out.push(run);
      }
      run.appendChild(b);
    } else {
      run = null;
      out.push(b);
    }
  }
  return out.map((b) => b.outerHTML).join('');
}

/** plain-text paste: blank line = paragraph, single newline = <br> */
export function plainTextToHtml(text: string): string {
  const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return text
    .split(/\r?\n\s*\r?\n/)
    .map((para) => para.trim())
    .filter(Boolean)
    .map((para) => `<p>${para.split(/\r?\n/).map(esc).join('<br>')}</p>`)
    .join('');
}

// ── Word/Docs heading promotion ────────────────────────────────────────────
// Word only emits real <h1-6> tags for its built-in "Heading 1/2/3" paragraph
// styles. Titles that were merely made bold/larger by hand arrive as
// <p class=MsoNormal><b><span style='font-size:16pt'>…</span></b></p> — plain
// paragraphs. TipTap (and the human eye) then treat them as body text. This
// promotes such "visual headings" back to real <hN> so pasted articles keep
// their structure. Real <h1-6> from Word/Google Docs already survive untouched.

const clampLevel = (n: number) => Math.max(2, Math.min(4, n)); // never a 2nd page-<h1>

/** largest font-size found on the paragraph or its runs, in px (0 = none set) */
function maxFontPx(el: Element): number {
  let max = 0;
  const scan = (node: Element) => {
    const m = (node.getAttribute('style') || '').match(/font-size\s*:\s*([\d.]+)\s*(pt|px)/i);
    if (m) {
      const px = m[2].toLowerCase() === 'pt' ? parseFloat(m[1]) * (4 / 3) : parseFloat(m[1]);
      if (px > max) max = px;
    }
    for (const c of Array.from(node.children)) scan(c);
  };
  scan(el);
  return max;
}

/** true when every bit of visible text in the paragraph sits in a bold context */
function textIsAllBold(p: Element): boolean {
  let hasText = false;
  let allBold = true;
  const visit = (node: Node, bold: boolean) => {
    if (node.nodeType === Node.TEXT_NODE) {
      if ((node.textContent || '').replace(/ /g, ' ').trim()) {
        hasText = true;
        if (!bold) allBold = false;
      }
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return;
    const el = node as Element;
    const st = (el.getAttribute('style') || '').toLowerCase();
    let b = bold;
    if (el.tagName === 'B' || el.tagName === 'STRONG') b = true;
    // boundary avoids matching Word's <b style='mso-bidi-font-weight:normal'>
    if (/(?:^|[;\s])font-weight\s*:\s*(bold|[6-9]00)/.test(st)) b = true;
    else if (/(?:^|[;\s])font-weight\s*:\s*(normal|[1-5]00)\b/.test(st)) b = false;
    for (const c of Array.from(el.childNodes)) visit(c, b);
  };
  visit(p, false);
  return hasText && allBold;
}

/** heading level a <p> should become, or null to leave it a paragraph */
function paragraphHeadingLevel(p: Element): number | null {
  const cls = p.getAttribute('class') || '';
  const st = (p.getAttribute('style') || '').toLowerCase();

  // explicit Word style markers
  if (/\bMsoTitle\b/i.test(cls)) return 2;
  if (/\bMsoSubtitle\b/i.test(cls)) return 3;
  let m = cls.match(/\bMsoHeading\s*([1-9])/i);
  if (m) return clampLevel(parseInt(m[1], 10));
  m = st.match(/mso-outline-level\s*:\s*([1-9])/);
  if (m) return clampLevel(parseInt(m[1], 10));

  // heuristic: a short, single-line, sentence-less run that is all bold or
  // clearly larger than body text reads as a heading
  const text = (p.textContent || '').replace(/ /g, ' ').trim();
  if (!text || text.length > 100) return null;
  if (/[.!?…]$/.test(text)) return null;              // ends like a sentence
  if (p.querySelector('br, img, ul, ol, table, a')) return null;
  const px = maxFontPx(p);
  if (textIsAllBold(p)) return px >= 21 ? 2 : 3;       // ~16pt → h2, else h3
  if (px >= 21) return 2;                              // big but not bold
  return null;
}

/** promote Word/Docs "visual heading" paragraphs to real <hN> tags */
export function promoteWordHeadings(html: string): string {
  if (typeof DOMParser === 'undefined' || !/<p[\s>]/i.test(html)) return html;
  const doc = new DOMParser().parseFromString(html, 'text/html');
  doc.body.querySelectorAll('p').forEach((p) => {
    const level = paragraphHeadingLevel(p);
    if (!level) return;
    const h = doc.createElement('h' + level);
    h.textContent = (p.textContent || '').replace(/ /g, ' ').trim();
    p.replaceWith(h);
  });
  return doc.body.innerHTML;
}

/** Session E large-paste: word count of normalized html */
export function wordCount(html: string): number {
  return html.replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;
}

/** apply a paragraph style's typography as inline styles on chosen heading
 *  levels (self-contained: publishes correctly with no style registry) */
export function mapHeadingsToStyles(
  html: string,
  mapping: Record<string, { properties: Record<string, any> } | null | undefined>,
): string {
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
  for (const [level, style] of Object.entries(mapping)) {
    if (!style) continue;
    const t = style.properties || {};
    const css: string[] = [];
    if (t.fontFamily) css.push(`font-family:${t.fontFamily}`);
    if (t.fontSize) css.push(`font-size:${t.fontSize}px`);
    if (t.fontWeight) css.push(`font-weight:${t.fontWeight}`);
    if (t.lineHeight) css.push(`line-height:${t.lineHeight}`);
    if (t.textColor) css.push(`color:${t.textColor}`);
    if (t.letterSpacing) css.push(`letter-spacing:${t.letterSpacing}em`);
    if (t.textTransform) css.push(`text-transform:${t.textTransform}`);
    if (!css.length) continue;
    doc.body.querySelectorAll(level.toLowerCase()).forEach((h) => h.setAttribute('style', css.join(';')));
  }
  return doc.body.innerHTML;
}
