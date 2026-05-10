import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { InlineTextField } from '@/components/editor/fields';

export const CtabannerPreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    heading: string;
    text: string;
    buttonText: string;
    buttonUrl: string;
    backgroundStyle: string;
    backgroundColor: string;
    backgroundImage: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
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
      <InlineTextField
        as="h3"
        value={data.heading || ''}
        placeholder="Add heading"
        onChange={(v) => update('heading', v)}
        className="text-lg font-bold mb-1 block"
      />
      <InlineTextField
        as="p"
        value={data.text || ''}
        placeholder="Add description..."
        onChange={(v) => update('text', v)}
        multiline
        className="text-sm opacity-90 mb-3 block"
      />
      <InlineTextField
        as="span"
        value={data.buttonText || ''}
        placeholder="Button text"
        onChange={(v) => update('buttonText', v)}
        className="inline-block px-4 py-1.5 bg-white/20 rounded text-sm font-medium"
      />
      {data.buttonUrl && data.buttonUrl !== '#' && (
        <span className="block mt-1 text-xs opacity-60">{data.buttonUrl}</span>
      )}
    </div>
  );
};
