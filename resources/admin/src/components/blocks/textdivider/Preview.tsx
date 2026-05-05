import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const widthMap: Record<string, string> = {
  full: '100%',
  half: '50%',
  quarter: '25%',
};

const styleContent: Record<string, string> = {
  line: '',
  dots: '\u00B7\u00B7\u00B7',
  asterisks: '* * *',
  dinkus: '***',
};

export const TextdividerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { style, customSymbol, width } = block.data as {
    style: string;
    customSymbol: string;
    width: string;
  };

  const maxWidth = widthMap[width] || widthMap.half;

  if (style === 'line') {
    return (
      <div className="flex justify-center py-4">
        <hr
          className="border-base-content/20"
          style={{ maxWidth, width: '100%' }}
        />
      </div>
    );
  }

  const symbol = customSymbol || styleContent[style] || styleContent.line;

  return (
    <div className="flex justify-center py-4">
      <span
        className="text-base-content/40 tracking-widest"
        style={{ maxWidth }}
      >
        {symbol}
      </span>
    </div>
  );
};
