import { describe, it, expect } from 'vitest';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { RichImage } from './RichImage';

function make(content: string) {
  return new Editor({ extensions: [StarterKit, Link, RichImage], content });
}

describe('RichImage node', () => {
  it('serialises a picked image with all settings to a <figure>', () => {
    const ed = make('<p>Hello</p>');
    ed.commands.insertRichImage({
      src: '/assets/files/abc.png', alt: 'A lamp', caption: 'Light sculpture', href: '/portfolio/',
      target: '_blank', align: 'float-right', width: '50%',
    });
    const html = ed.getHTML();
    expect(html).toContain('<figure class="rt-figure" data-align="float-right" style="width: 50%;">');
    expect(html).toContain('<a href="/portfolio/" target="_blank" rel="noopener"><img src="/assets/files/abc.png" alt="A lamp"></a>');
    expect(html).toContain('<figcaption>Light sculpture</figcaption>');
  });

  it('omits link/caption wrappers when not set', () => {
    const ed = make('');
    ed.commands.insertRichImage({ src: '/x.jpg', alt: '' });
    // (StarterKit's TrailingNode appends an empty paragraph after a block atom)
    expect(ed.getHTML()).toBe('<figure class="rt-figure" data-align="center"><img src="/x.jpg" alt=""></figure><p></p>');
  });

  it('round-trips its own output', () => {
    const src = '<figure class="rt-figure" data-align="left" style="width: 33%;"><a href="https://e.eu"><img src="/y.webp" alt="Y" title="T"></a><figcaption>Cap</figcaption></figure>';
    const ed = make(src);
    expect(ed.getHTML()).toBe(src);
    expect(ed.getJSON().content?.[0]).toMatchObject({ type: 'richImage', attrs: { src: '/y.webp', alt: 'Y', title: 'T', caption: 'Cap', href: 'https://e.eu', align: 'left', width: '33%' } });
  });

  it('upgrades legacy bare <img> (even inside a link and paragraph) to a figure, keeping the link', () => {
    const ed = make('<p>Before <a href="/go/"><img src="/old.png" alt="old"></a> after</p>');
    const json = ed.getJSON();
    const img = json.content?.find(n => n.type === 'richImage');
    expect(img?.attrs).toMatchObject({ src: '/old.png', alt: 'old', href: '/go/', align: 'center' });
    expect(ed.getHTML()).toContain('<figure class="rt-figure" data-align="center"><a href="/go/"><img src="/old.png" alt="old"></a></figure>');
  });

  it('keeps a px width from the size presets / custom input', () => {
    const ed = make('');
    ed.commands.insertRichImage({ src: '/p.jpg', alt: '', width: '480px' });
    expect(ed.getHTML()).toContain('<figure class="rt-figure" data-align="center" style="width: 480px;">');
    const again = make(ed.getHTML());
    expect(again.getJSON().content?.[0]?.attrs?.width).toBe('480px');
  });

  it('ignores pixel width / align attributes from legacy markup', () => {
    const ed = make('<img src="/z.png" width="1920" height="1080" align="left">');
    expect(ed.getJSON().content?.[0]?.attrs).toMatchObject({ src: '/z.png', width: null, align: 'center' });
    expect(ed.getHTML()).not.toContain('1920');
  });

  it('updates attributes of the selected image', () => {
    const ed = make('<figure class="rt-figure" data-align="center"><img src="/a.png" alt=""></figure>');
    ed.commands.setNodeSelection(0);
    expect(ed.isActive('richImage')).toBe(true);
    ed.commands.updateRichImage({ alt: 'new alt', caption: 'c', align: 'full' });
    expect(ed.getHTML()).toContain('<figure class="rt-figure" data-align="full"><img src="/a.png" alt="new alt"><figcaption>c</figcaption></figure>');
  });
});
