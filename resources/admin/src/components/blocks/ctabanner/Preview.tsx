import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const CtabannerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    heading: string;
    text: string;
    buttonText: string;
    buttonUrl: string;
    backgroundStyle: string;
    backgroundColor: string;
    backgroundImage: string;
  };

  const bgColor = data.backgroundColor || '#3b82f6';
  const bgStyle: React.CSSProperties =
    data.backgroundStyle === 'gradient'
      ? { background: `linear-gradient(135deg, ${bgColor}, ${bgColor}cc)` }
      : data.backgroundStyle === 'image' && data.backgroundImage
        ? { backgroundImage: `url(${data.backgroundImage})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { backgroundColor: bgColor };

  return (
    <div
      className="rounded-lg p-6 text-center text-white"
      style={{ ...bgStyle, minHeight: 80 }}
    >
      <h3 className="text-lg font-bold mb-1">{data.heading || 'Ready to get started?'}</h3>
      {data.text && <p className="text-sm opacity-90 mb-3">{data.text}</p>}
      <span className="inline-block px-4 py-1.5 bg-white/20 rounded text-sm font-medium">
        {data.buttonText || 'Get started'}
      </span>
      {data.buttonUrl && data.buttonUrl !== '#' && (
        <span className="block mt-1 text-xs opacity-60">{data.buttonUrl}</span>
      )}
    </div>
  );
};
