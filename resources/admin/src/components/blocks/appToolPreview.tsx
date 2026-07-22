import React from 'react';

/**
 * Shared builder-canvas preview for the interactive app-blocks. The real
 * widget is powered by the self-hosted app-tools runtime on the published site;
 * in the editor we show a clean, recognizable resting-state card with a summary
 * of the block's key settings.
 */
export const AppToolPreview: React.FC<{
  badge: string;
  eyebrow?: string;
  title: string;
  summary: string;
  accent?: string;
}> = ({ badge, eyebrow, title, summary, accent = '#183129' }) => (
  <div
    className="rounded-2xl border p-5 my-2"
    style={{ background: '#f4f0e7', borderColor: '#e2dccf', color: '#202622', maxWidth: 640 }}
  >
    <div className="flex items-center justify-between mb-2">
      {eyebrow ? (
        <span className="text-[11px] uppercase tracking-widest" style={{ color: '#5b635e' }}>{eyebrow}</span>
      ) : <span />}
      <span
        className="text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full"
        style={{ background: accent, color: '#f4f0e7' }}
      >
        {badge}
      </span>
    </div>
    <div className="text-lg font-semibold" style={{ color: '#202622' }}>{title}</div>
    <div className="text-sm mt-1" style={{ color: '#5b635e' }}>{summary}</div>
    <div className="mt-3 text-[11px]" style={{ color: '#9a958a' }}>Interactive on the published site</div>
  </div>
);
