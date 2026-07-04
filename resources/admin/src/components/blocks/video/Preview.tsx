import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const ytId = (u: string) => u.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/)?.[1];
const vimeoId = (u: string) => u.match(/vimeo\.com\/(\d+)/)?.[1];

const SHAPE_RADIUS: Record<string, string> = {
  none: '0', rounded: '2rem', capsule: '999px', circle: '50%',
};

/**
 * Real playing preview: direct files render an actual <video> (muted, looping)
 * filling the block box — resizable/shapeable on the slider canvas exactly
 * like the published output. YouTube/Vimeo render their embed iframe.
 */
export const VideoPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const url = (data.url as string) || '';
  const shape = (data.shape as string) || 'none';
  const borderRadius = shape === 'custom'
    ? ((data.shapeRadius as string) || '0')
    : (SHAPE_RADIUS[shape] ?? '0');

  if (!url) {
    return (
      <div className="w-full h-full min-h-[80px] bg-gray-900 border-2 border-dashed border-gray-600 flex flex-col items-center justify-center text-gray-400 p-4"
        style={{ borderRadius }}>
        <div className="text-2xl">▶</div>
        <span className="text-xs italic">No video — pick a file or URL in the panel</span>
      </div>
    );
  }

  const yt = ytId(url);
  const vm = vimeoId(url);
  if (yt || vm) {
    const src = yt
      ? `https://www.youtube-nocookie.com/embed/${yt}?mute=1`
      : `https://player.vimeo.com/video/${vm}?muted=1`;
    return (
      <div className="w-full h-full min-h-[80px] overflow-hidden" style={{ borderRadius }}>
        <iframe src={src} className="w-full h-full pointer-events-none" style={{ minHeight: 80 }}
          title="Video preview" allow="autoplay; encrypted-media" />
      </div>
    );
  }

  return (
    <video
      src={url}
      poster={(data.poster as string) || undefined}
      muted loop autoPlay playsInline
      className="w-full h-full object-cover block pointer-events-none"
      style={{ borderRadius, minHeight: 60 }}
    />
  );
};
