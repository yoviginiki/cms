import { Node } from '@tiptap/core';

/**
 * Block-level image node for the WYSIWYG editor.
 *
 * Replaces @tiptap/extension-image so an image carries everything an editor
 * expects to set on it — alt, caption, link, alignment, width — and serialises
 * to a plain <figure> that the published site can style without any runtime:
 *
 *   <figure class="rt-figure" data-align="center" style="width:50%">
 *     <a href="…"><img src="…" alt="…"></a>
 *     <figcaption>…</figcaption>
 *   </figure>
 *
 * Legacy content with a bare <img> (or <a><img></a>) still parses; it just
 * becomes a figure the next time the block is saved.
 */

export type RichImageAlign = 'left' | 'center' | 'right' | 'full' | 'float-left' | 'float-right';

export interface RichImageAttrs {
  src: string;
  alt?: string;
  title?: string | null;
  caption?: string;
  href?: string | null;
  target?: string | null;
  align?: RichImageAlign;
  width?: string | null;
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    richImage: {
      insertRichImage: (attrs: RichImageAttrs) => ReturnType;
      updateRichImage: (attrs: Partial<RichImageAttrs>) => ReturnType;
    };
  }
}

const ALIGNS: RichImageAlign[] = ['left', 'center', 'right', 'full', 'float-left', 'float-right'];

/** Accepts "50%" (legacy) or "480px"; anything else (e.g. a bare intrinsic width="1920") is ignored. */
function cssWidth(value: string | null | undefined): string | null {
  if (!value) return null;
  const v = value.trim();
  return /^\d{1,3}%$/.test(v) || /^\d{2,4}px$/.test(v) ? v : null;
}

export const RichImage = Node.create({
  name: 'richImage',
  group: 'block',
  atom: true,
  draggable: true,
  selectable: true,

  addAttributes() {
    // Every attribute is resolved by the parse rules below (from the <figure>,
    // its <img>, <a> and <figcaption>), never by Tiptap's default per-attribute
    // getAttribute() — otherwise a legacy <img width="1920"> or align="left"
    // would override the computed value. Likewise renderHTML() builds the
    // markup from node.attrs, so nothing here is emitted as a raw attribute.
    const own = <T>(def: T) => ({ default: def, parseHTML: () => null, renderHTML: () => ({}) });
    return {
      src: own<string | null>(null),
      alt: own(''),
      title: own<string | null>(null),
      caption: own(''),
      href: own<string | null>(null),
      target: own<string | null>(null),
      align: own<RichImageAlign>('center'),
      width: own<string | null>(null),
    };
  },

  parseHTML() {
    return [
      {
        tag: 'figure',
        getAttrs: (el) => {
          const fig = el as HTMLElement;
          const img = fig.querySelector('img');
          if (!img) return false;
          const a = img.closest('a');
          const cap = fig.querySelector('figcaption');
          const align = fig.getAttribute('data-align') as RichImageAlign | null;
          return {
            src: img.getAttribute('src'),
            alt: img.getAttribute('alt') || '',
            title: img.getAttribute('title'),
            caption: cap?.textContent?.trim() || '',
            href: a?.getAttribute('href') || null,
            target: a?.getAttribute('target') || null,
            align: align && ALIGNS.includes(align) ? align : 'center',
            width: cssWidth(fig.style.width) ?? cssWidth(img.style.width) ?? cssWidth(img.getAttribute('width')),
          };
        },
      },
      {
        tag: 'img[src]',
        getAttrs: (el) => {
          const img = el as HTMLElement;
          const a = img.closest('a');
          return {
            src: img.getAttribute('src'),
            alt: img.getAttribute('alt') || '',
            title: img.getAttribute('title'),
            caption: '',
            href: a?.getAttribute('href') || null,
            target: a?.getAttribute('target') || null,
            align: 'center',
            width: cssWidth(img.style.width) ?? cssWidth(img.getAttribute('width')),
          };
        },
      },
    ];
  },

  renderHTML({ node }) {
    const { src, alt, title, caption, href, target, align, width } = node.attrs as RichImageAttrs;
    const img: any = ['img', { src, alt: alt || '', title: title || null }];
    const inner = href
      ? ['a', { href, target: target || null, rel: target === '_blank' ? 'noopener' : null }, img]
      : img;
    const figAttrs = {
      class: 'rt-figure',
      'data-align': align || 'center',
      style: width ? `width:${width}` : null,
    };
    return caption
      ? ['figure', figAttrs, inner, ['figcaption', {}, caption]]
      : ['figure', figAttrs, inner];
  },

  addCommands() {
    return {
      insertRichImage: (attrs) => ({ commands }) => commands.insertContent({ type: this.name, attrs }),
      updateRichImage: (attrs) => ({ commands }) => commands.updateAttributes(this.name, attrs),
    };
  },
});
