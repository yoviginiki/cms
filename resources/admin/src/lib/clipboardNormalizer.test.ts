// GOLDEN TESTS — clipboard normalizer (W1-3): Word / Google Docs / web soup
// reduces to the magazine content model with zero styling residue.

import { describe, it, expect } from 'vitest';
import { normalizeClipboardHtml, plainTextToHtml, promoteWordHeadings } from './clipboardNormalizer';

// Representative (abridged) real-world payload shapes
const WORD_HTML = `
<html xmlns:o="urn:schemas-microsoft-com:office:office"><head>
<style>p.MsoNormal{margin:0cm;font-size:11.0pt}</style></head>
<body lang=EN-US style='tab-interval:36.0pt'>
<!--[if gte mso 9]><xml><o:shapedefaults/></xml><![endif]-->
<p class=MsoNormal style='margin-bottom:8pt'>First <b>bold</b> paragraph with
<span style='color:#FF0000'>colored</span> text.<o:p></o:p></p>
<p class=MsoTitle style='font-size:20pt'>Not a real heading tag</p>
<h2 style='mso-style-link:"Heading 2 Char"'>A Word heading</h2>
<p class=MsoListParagraph style='text-indent:-18pt'>·<span style='font:7pt "Times"'>&nbsp;&nbsp;</span>Bullet-ish line</p>
<p class=MsoNormal><i>Italic</i> and <u>underline</u> and a
<a href="https://example.com/x">link</a> and <a href="javascript:alert(1)">bad link</a>.</p>
</body></html>`;

const GDOCS_HTML = `
<meta charset="utf-8"><b style="font-weight:normal;" id="docs-internal-guid-abc123">
<p dir="ltr" style="line-height:1.38;margin-top:0pt;"><span style="font-size:11pt;font-family:Arial;font-weight:400;">Plain gdocs text </span><span style="font-size:11pt;font-weight:700;">bold span</span><span style="font-style:italic;"> italic span</span></p>
<h1 dir="ltr" style="line-height:1.38;"><span style="font-size:20pt;font-weight:400;">Doc Heading</span></h1>
<ul style="margin-top:0;"><li dir="ltr" style="list-style-type:disc;"><p dir="ltr" style="line-height:1.38;"><span>Item one</span></p></li>
<li dir="ltr"><p dir="ltr"><span>Item two</span></p></li></ul>
<p dir="ltr"><span style="font-weight:700;font-style:italic;">bold italic</span></p>
</b><br class="Apple-interchange-newline">`;

describe('clipboard normalizer (W1-3)', () => {
  it('Word: keeps structure, drops mso classes/styles/comments/o:p', () => {
    const out = normalizeClipboardHtml(WORD_HTML);
    expect(out).not.toMatch(/Mso|mso-|<o:p>|class=|style=|<!--/);
    expect(out).toContain('<strong>bold</strong>');
    expect(out).toContain('colored'); // span unwrapped, text kept
    expect(out).toContain('<h2>A Word heading</h2>');
    expect(out).toContain('Bullet-ish line'); // MsoListParagraph → p
    expect(out).toContain('<em>Italic</em>');
    expect(out).toContain('<u>underline</u>');
    expect(out).toContain('href="https://example.com/x"');
    expect(out).not.toContain('javascript:'); // unsafe link unwrapped to text
    expect(out).toContain('bad link');
  });

  it('Google Docs: unwraps guid wrapper, converts styled spans to strong/em', () => {
    const out = normalizeClipboardHtml(GDOCS_HTML);
    expect(out).not.toMatch(/docs-internal-guid|style=|dir=/);
    expect(out).toContain('<strong>bold span</strong>');
    expect(out).toContain('<em> italic span</em>');
    expect(out).toContain('<h1>Doc Heading</h1>');
    expect(out).toContain('<ul><li>Item one</li><li>Item two</li></ul>');
    expect(out).toContain('<strong><em>bold italic</em></strong>');
    // the font-weight:normal guid <b> must NOT become <strong>
    expect(out).not.toContain('<strong>Plain gdocs text');
  });

  it('loose inline content at root is wrapped into a paragraph', () => {
    const out = normalizeClipboardHtml('just some <b>text</b> without blocks');
    expect(out).toBe('<p>just some <strong>text</strong> without blocks</p>');
  });

  it('nested divs unwrap; empty paragraphs are dropped', () => {
    const out = normalizeClipboardHtml('<div><div><p>real</p><p>   </p><p></p></div></div>');
    expect(out).toBe('<p>real</p>');
  });

  it('images survive with safe src only', () => {
    const out = normalizeClipboardHtml('<p><img src="https://x.test/a.png" alt="A" onerror="x()"><img src="data:image/png;base64,xx"></p>');
    expect(out).toContain('<img src="https://x.test/a.png" alt="A">');
    expect(out).not.toContain('data:');
    expect(out).not.toContain('onerror');
  });

  it('blockquote and table flattening', () => {
    const out = normalizeClipboardHtml('<blockquote><p>quoted words</p></blockquote><table><tr><td>cell one</td><td>cell two</td></tr></table>');
    expect(out).toContain('quoted words');
    expect(out).toContain('cell one');
    expect(out).toContain('cell two');
    expect(out).not.toContain('<table'); // flattened (tables are a later track)
  });

  it('promoteWordHeadings: hand-bolded Word title becomes a real heading', () => {
    const out = promoteWordHeadings(
      `<p class=MsoNormal><b style='mso-bidi-font-weight:normal'><span style='font-size:16.0pt'>Практиката е патерица</span></b></p>` +
      `<p class=MsoNormal>Ще го кажа направо, защото това е най-важното, което съм научил.</p>`
    );
    expect(out).toContain('<h2>Практиката е патерица</h2>');
    expect(out).toContain('<p'); // body paragraph is left alone
    expect(out).not.toMatch(/<h2>[^<]*най-важното/);
  });

  it('promoteWordHeadings: short bold line without a large font → h3', () => {
    const out = promoteWordHeadings(`<p><b>Една проста практика</b></p>`);
    expect(out).toContain('<h3>Една проста практика</h3>');
  });

  it('promoteWordHeadings: Mso style markers and outline levels map to headings', () => {
    expect(promoteWordHeadings(`<p class=MsoTitle>Вътрешният храм преди външния</p>`))
      .toContain('<h2>Вътрешният храм преди външния</h2>');
    expect(promoteWordHeadings(`<p style='mso-outline-level:3'>Границите и милостта</p>`))
      .toContain('<h3>Границите и милостта</h3>');
  });

  it('promoteWordHeadings: leaves ordinary and inline-emphasis paragraphs alone', () => {
    // a bold word inside a full sentence must not be promoted
    const sentence = `<p>Umъt <b>tича</b> напред, тялото изостава уморено, а духът стои забравен.</p>`;
    expect(promoteWordHeadings(sentence)).toBe(sentence);
    // an all-bold paragraph that ends like a sentence stays a paragraph
    const emph = `<p><b>Това е важно изречение, което завършва с точка.</b></p>`;
    expect(promoteWordHeadings(emph)).toContain('<p>');
    expect(promoteWordHeadings(emph)).not.toContain('<h');
  });

  it('promoteWordHeadings: real Word <h1> tags pass through untouched', () => {
    expect(promoteWordHeadings(`<h1>The Annual Report</h1><p>Body text ends here.</p>`))
      .toBe(`<h1>The Annual Report</h1><p>Body text ends here.</p>`);
  });

  it('plain text: blank lines split paragraphs, newlines become <br>', () => {
    expect(plainTextToHtml('one\ntwo\n\nthree')).toBe('<p>one<br>two</p><p>three</p>');
    expect(plainTextToHtml('a < b & c')).toBe('<p>a &lt; b &amp; c</p>');
  });
});
