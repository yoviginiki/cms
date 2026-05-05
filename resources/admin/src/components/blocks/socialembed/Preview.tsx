import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const platformIcons: Record<string, string> = {
  twitter: '𝕏',
  instagram: '📷',
  youtube: '▶',
  tiktok: '♪',
  auto: '🔗',
};

function detectPlatform(url: string): string {
  if (!url) return 'auto';
  if (url.includes('twitter.com') || url.includes('x.com')) return 'twitter';
  if (url.includes('instagram.com')) return 'instagram';
  if (url.includes('youtube.com') || url.includes('youtu.be')) return 'youtube';
  if (url.includes('tiktok.com')) return 'tiktok';
  return 'auto';
}

export const SocialembedPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    url: string;
    platform: string;
  };

  const platform = data.platform === 'auto' ? detectPlatform(data.url || '') : data.platform;
  const icon = platformIcons[platform] || platformIcons.auto;

  return (
    <div className="rounded border border-gray-200 p-4 flex items-center gap-3">
      <span className="text-2xl">{icon}</span>
      <div className="flex-1 min-w-0">
        <div className="text-xs font-medium text-gray-500 uppercase mb-0.5">
          {platform === 'auto' ? 'Social' : platform} embed
        </div>
        {data.url ? (
          <div className="text-sm text-blue-600 truncate">{data.url}</div>
        ) : (
          <div className="text-sm text-gray-400 italic">No URL set</div>
        )}
      </div>
    </div>
  );
};
