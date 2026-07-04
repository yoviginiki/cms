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
  return {
    bold: /font-weight\s*:\s*(bold|[7-9]00)/.test(st),
    italic: /font-style\s*:\s*italic/.test(st),
    normal: /font-weight\s*:\s*(normal|400)/.test(st),
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
  if (isMsoListParagraph(el)) outTag = 'p';
  if (isHeading) outTag = tag.toLowerCase();
  const block = doc.createElement(outTag);
  inline.forEach((n) => block.appendChild(n));
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
  return blocks.map((b) => b.outerHTML).join('');
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
