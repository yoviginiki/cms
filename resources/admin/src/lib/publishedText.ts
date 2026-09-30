// What the published site keeps of a text block's HTML. Publish runs it
// through HTMLPurifier (SanitizationService rich profile) which drops every
// inline `style` — text pasted from Word/Google Docs/ChatGPT carries its own
// font, size, line-height and margins on each paragraph, so the editor must
// drop them too or it shows a different font/size than the live page.

export function publishedTextHtml(html: string): string {
  if (!html || (!html.includes('style=') && !/<font\b/i.test(html))) return html;
  const tpl = document.createElement('template');
  tpl.innerHTML = html;
  tpl.content.querySelectorAll('[style]').forEach(el => el.removeAttribute('style'));
  tpl.content.querySelectorAll('font').forEach(el => el.replaceWith(...Array.from(el.childNodes)));
  return tpl.innerHTML;
}
