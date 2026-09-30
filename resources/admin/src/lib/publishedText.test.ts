import { describe, it, expect } from 'vitest';
import { publishedTextHtml } from './publishedText';

describe('publishedTextHtml', () => {
  it('drops pasted inline styles and <font>, keeps structure', () => {
    expect(publishedTextHtml('<p style="font-family: Arial; line-height: 1.08"><span style="color:red">Hi</span> <font face="x">there</font></p>'))
      .toBe('<p><span>Hi</span> there</p>');
  });
  it('leaves clean html untouched', () => {
    expect(publishedTextHtml('<p><strong>a</strong></p>')).toBe('<p><strong>a</strong></p>');
  });
});
