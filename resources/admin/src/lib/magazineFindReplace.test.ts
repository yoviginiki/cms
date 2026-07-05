import { describe, it, expect } from 'vitest';
import { findMatches, replaceInHtml } from './magazineFindReplace';

const pageWith = (html: string, type = 'text_frame') => [{
  id: 'p1', pageNumber: 1, isMaster: false,
  elements: [{ id: 'e1', type, y: 0, x: 0, data: { content: html } }],
} as any];

describe('find & replace (W3)', () => {
  it('finds case-insensitively by default, case-sensitive on demand', () => {
    const pages = pageWith('<p>The Cat sat. the cat ran.</p>');
    expect(findMatches(pages, 'the cat')).toHaveLength(2);
    expect(findMatches(pages, 'the cat', { matchCase: true })).toHaveLength(1);
  });

  it('never matches inside markup', () => {
    const pages = pageWith('<p class="classy">text</p>');
    expect(findMatches(pages, 'class')).toHaveLength(0);
  });

  it('replaceInHtml preserves tags and replaces all', () => {
    const { html, replaced } = replaceInHtml('<p>foo <b>keep</b> foo</p>', 'foo', 'bar');
    expect(replaced).toBe(2);
    expect(html).toBe('<p>bar <b>keep</b> bar</p>');
  });

  it('replaces only the nth occurrence when asked', () => {
    const { html, replaced } = replaceInHtml('<p>x a x a x</p>', 'a', 'B', { occurrence: 1 });
    expect(replaced).toBe(1);
    expect(html).toBe('<p>x a x B x</p>');
  });

  it('keeps original casing of untouched matches (case-insensitive replace)', () => {
    const { html } = replaceInHtml('<p>Word word</p>', 'word', 'term', { occurrence: 0 });
    expect(html).toBe('<p>term word</p>');
  });

  it('skips non-text frames and empty queries', () => {
    expect(findMatches(pageWith('<p>hello</p>', 'image_frame'), 'hello')).toHaveLength(0);
    expect(findMatches(pageWith('<p>hello</p>'), '')).toHaveLength(0);
    expect(replaceInHtml('<p>x</p>', '', 'y').replaced).toBe(0);
  });
});

describe('cross-slice find (final polish)', () => {
  it('detects a phrase straddling two thread slices, flags it non-replaceable', () => {
    const pages = [{
      id: 'p1', pageNumber: 1, isMaster: false,
      elements: [
        { id: 'a', type: 'text_frame', y: 0, x: 0, threadId: 't1', threadOrder: 0, data: { content: '<p>the story ends with margin</p>' } },
        { id: 'b', type: 'text_frame', y: 100, x: 0, threadId: 't1', threadOrder: 1, data: { content: '<p>vermilion continues the tale</p>' } },
      ],
    }] as any;
    const matches = findMatches(pages, 'margin vermilion');
    expect(matches).toHaveLength(1);
    expect(matches[0].crossSlice).toBe(true);
    expect(matches[0].elementId).toBe('a');
    expect(matches[0].preview).toContain('spans frames');
    expect(findMatches(pages, 'vermilion').filter((m) => m.crossSlice)).toHaveLength(0);
  });
});
