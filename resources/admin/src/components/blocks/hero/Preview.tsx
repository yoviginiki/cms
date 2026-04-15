import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const HeroPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { title, subtitle, ctaText, ctaUrl } = block.data as {
    title: string;
    subtitle: string;
    ctaText: string;
    ctaUrl: string;
  };

  if (!title) {
    return (
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-12 rounded-lg flex items-center justify-center">
        <p className="text-lg opacity-60">No title set</p>
      </div>
    );
  }

  return (
    <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-12 rounded-lg text-center">
      <h1 className="text-4xl font-bold mb-4">{title}</h1>
      {subtitle && <p className="text-xl opacity-90 mb-6">{subtitle}</p>}
      {ctaText && (
        <a
          href={ctaUrl || '#'}
          className="inline-block bg-white text-blue-600 font-semibold px-6 py-3 rounded-lg hover:bg-opacity-90 transition-colors"
        >
          {ctaText}
        </a>
      )}
    </div>
  );
};
