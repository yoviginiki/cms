// GOLDEN TESTS — story content model (parse / slice / re-join losslessness)

import { describe, it, expect } from 'vitest';
import { parseStory, sliceStory, storyWords, htmlWords, joinSlices, toFlowBlocks } from './content';
import { buildWordPrefix } from './types';

const SAMPLE =
  '<h2>The Substrate</h2>' +
  '<p>Every sheet begins with <b>a kilo of vermilion</b> pigment, and the <i>folded signature</i> resists every shortcut the layout artist takes.</p>' +
  '<blockquote>Print is patient; screens forgive everything.</blockquote>' +
  '<p>Second paragraph with a <a href="https://example.com">link inside</a> and more words to split across frames cleanly.</p>';

describe('story content model', () => {
  it('parses blocks with kinds and word offsets', () => {
    const story = parseStory(SAMPLE);
    expect(story.map((b) => b.kind)).toEqual(['heading', 'paragraph', 'quote', 'paragraph']);
    expect(story[0].keepWithNext).toBe(true);
    expect(story[1].words.length).toBeGreaterThan(10);
  });

  it('LOSSLESS: bare root text nodes are wrapped in <p>, never dropped', () => {
    const story = parseStory('<p>one two</p>loose words here<p>three</p>');
    expect(story).toHaveLength(3);
    expect(storyWords(story).join(' ')).toBe('one two loose words here three');
  });

  it('LOSSLESS: slicing partitions reproduce every word exactly once', () => {
    const story = parseStory(SAMPLE);
    const total = buildWordPrefix(toFlowBlocks(story)).slice(-1)[0];
    for (const cut1 of [3, 7, 12, 20]) {
      for (const cut2 of [Math.min(total, cut1 + 9), Math.min(total, cut1 + 15)]) {
        const parts = [
          sliceStory(story, 0, cut1),
          sliceStory(story, cut1, cut2),
          sliceStory(story, cut2, total),
        ];
        const rejoined = parts.map(htmlWords).flat();
        expect(rejoined).toEqual(storyWords(story));
      }
    }
  });

  it('inline markup survives a mid-element split', () => {
    const story = parseStory('<p>alpha <b>bold one bold two</b> omega</p>');
    const html1 = sliceStory(story, 0, 3); // alpha bold one
    const html2 = sliceStory(story, 3, 6); // bold two omega
    expect(html1).toContain('<b>');
    expect(html2).toContain('<b>');
    expect(htmlWords(html1).concat(htmlWords(html2))).toEqual(['alpha', 'bold', 'one', 'bold', 'two', 'omega']);
  });

  it('continued fragments carry flow markers and margin resets', () => {
    const story = parseStory('<p>one two three four five six</p>');
    const mid = sliceStory(story, 2, 4);
    expect(mid).toContain('data-flow-cont="in-out"');
    expect(mid).toContain('margin-top: 0');
  });

  it('joinSlices re-joins split paragraphs back into one block', () => {
    const story = parseStory(SAMPLE);
    const total = buildWordPrefix(toFlowBlocks(story)).slice(-1)[0];
    const slices = [sliceStory(story, 0, 9), sliceStory(story, 9, 17), sliceStory(story, 17, total)];
    const joined = joinSlices(slices);
    const reparsed = parseStory(joined);
    // same block structure as the original story
    expect(reparsed.map((b) => b.kind)).toEqual(['heading', 'paragraph', 'quote', 'paragraph']);
    expect(storyWords(reparsed)).toEqual(storyWords(story));
    expect(joined).not.toContain('data-flow-cont');
  });

  it('word-less atomic blocks (img/hr) survive slicing', () => {
    const story = parseStory('<p>before</p><img src="/x.png" alt=""><p>after words</p>');
    const total = buildWordPrefix(toFlowBlocks(story)).slice(-1)[0];
    expect(total).toBe(4); // before + img token + after + words
    const all = sliceStory(story, 0, total);
    expect(all).toContain('<img');
    expect(htmlWords(all)).toEqual(['before', 'after', 'words']);
  });

  it('lists are atomic blocks', () => {
    const story = parseStory('<p>intro</p><ul><li>one</li><li>two</li></ul>');
    expect(story[1].kind).toBe('atomic');
    // textContent concatenates adjacent <li> runs — fine: atomic blocks are
    // never split, so their word list only feeds token counting, and slicing
    // re-parses identically (self-consistent losslessness).
    expect(story[1].words).toEqual(['onetwo']);
  });
});
