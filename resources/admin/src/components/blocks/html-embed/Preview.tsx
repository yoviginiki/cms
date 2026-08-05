import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

/**
 * Render embedded HTML inside a sandboxed iframe so page-global CSS in the
 * embed can't leak out and hijack the admin editor chrome.
 *
 * The previous implementation injected the raw HTML with
 * `dangerouslySetInnerHTML` straight into the editor DOM. A full-page embed
 * (e.g. a "bare" fullscreen homepage) ships global rules like
 * `html, body { overflow: hidden }` + `.stage { position: fixed; inset: 0 }`,
 * which painted a white overlay over the whole editor and killed scrolling —
 * making the editor look like it never loaded. The iframe boundary isolates
 * those styles. `sandbox="allow-same-origin"` lets same-origin assets (fonts,
 * images) load while keeping scripts disabled, so the embed can't touch the
 * parent document.
 */
export const HtmlEmbedPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { html: string };
  const frameRef = React.useRef<HTMLIFrameElement>(null);
  const [height, setHeight] = React.useState<number | null>(null);

  // Full-page embeds (fixed/absolute overlays, 100vh/100vw) contribute no
  // natural flow height, so auto-sizing collapses them to ~0. Give those a
  // 16:9 viewport box instead; normal in-flow embeds auto-size to content.
  const fullscreen = /position\s*:\s*fixed|inset\s*:\s*0|(?:width|height)\s*:\s*100v/i.test(
    data.html || '',
  );

  const measure = React.useCallback(() => {
    const doc = frameRef.current?.contentDocument;
    if (!doc?.body) return;
    const h = Math.max(doc.body.scrollHeight, doc.documentElement?.scrollHeight ?? 0);
    if (h > 0) setHeight(Math.min(h, 2400));
  }, []);

  if (!data.html) {
    return (
      <div className="rounded border border-dashed border-gray-300 p-4 text-center text-sm text-gray-400 italic">
        No HTML content
      </div>
    );
  }

  return (
    <div
      className="rounded border border-gray-200 overflow-hidden bg-white"
      style={fullscreen ? { aspectRatio: '16 / 9' } : undefined}
    >
      <iframe
        ref={frameRef}
        srcDoc={data.html}
        onLoad={measure}
        sandbox="allow-same-origin"
        title="HTML embed preview"
        className="block w-full"
        style={{ height: fullscreen ? '100%' : (height ?? 120), border: 0 }}
      />
    </div>
  );
};
