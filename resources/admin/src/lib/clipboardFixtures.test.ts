// Session E: REAL-WORLD-SHAPED clipboard fixtures (Word 15 mso markup, Google
// Docs docs-internal-guid wrapper, Medium-style web article) run through the
// normalizer with structural expectations. If a vendor changes their clipboard
// format, capture a fresh fixture here.
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { join } from 'path';
import { normalizeClipboardHtml, mapHeadingsToStyles, wordCount } from './clipboardNormalizer';

const fx = (name: string) => readFileSync(join(__dirname, '__fixtures__/clipboard', name), 'utf8');
const clean = (name: string) => normalizeClipboardHtml(fx(name));

const NO_RESIDUE = (html: string) => {
  expect(html).not.toMatch(/mso-|MsoNormal|WordSection|<o:p|docs-internal-guid|<script|<style|class=|<span/i);
};

describe('real-world clipboard fixtures (Session E)', () => {
  it('Word: heading, bold/italic, numbered list survive; mso junk dies', () => {
    const html = clean('word.html');
    NO_RESIDUE(html);
    expect(html).toMatch(/<h1>[^<]*The Annual Report/);
    expect(html).toContain('<strong>Revenue grew</strong>');
    expect(html).toContain('<em>all divisions</em>');
    expect(html).toContain('First numbered point');
    // Word list paragraphs degrade to plain <p> (documented v1 limit)
  });

  it('Google Docs: heading + inline weight/style spans → semantic b/i', () => {
    const html = clean('gdocs.html');
    NO_RESIDUE(html);
    expect(html).toMatch(/<h2>[^<]*Quarterly Notes/);
    expect(html).toContain('<strong>Bold opener</strong>');
    expect(html).toContain('<em> then italic continuation</em>');
    expect(html).toMatch(/<ul>[\s\S]*Bullet one[\s\S]*Bullet two[\s\S]*<\/ul>/);
  });

  it('Web article: figure+caption kept, script/style dead, entities kept', () => {
    const html = clean('web.html');
    NO_RESIDUE(html);
    expect(html).toMatch(/<h1>[^<]*Why Substrates Matter/);
    expect(html).toMatch(/<figure><img src="https:[^"]+" alt="A printing press"><figcaption>The press at dawn<\/figcaption><\/figure>/);
    expect(html).toContain('<strong>substrate</strong>');
    expect(html).toContain('<em>memory</em>');
    expect(html).toContain('<blockquote>');
    expect(html).not.toContain('trackRead');
  });
});

describe('large-paste helpers (Session E)', () => {
  it('wordCount ignores tags', () => {
    expect(wordCount('<p>one <b>two</b> three</p>')).toBe(3);
  });
  it('mapHeadingsToStyles inlines the style typography on chosen levels only', () => {
    const out = mapHeadingsToStyles('<h1>Title</h1><h2>Sub</h2><p>body</p>', {
      h1: { properties: { fontFamily: 'Playfair Display', fontSize: 32, textColor: '#e63b2e' } },
    });
    expect(out).toContain('<h1 style="font-family:Playfair Display;font-size:32px;color:#e63b2e">Title</h1>');
    expect(out).toContain('<h2>Sub</h2>');
    expect(out).toContain('<p>body</p>');
  });
});
