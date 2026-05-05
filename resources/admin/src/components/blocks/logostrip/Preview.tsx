import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const LogostripPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { logos, grayscale, gap } = block.data as {
    logos: string[];
    grayscale: boolean;
    gap: string;
  };

  const logoList = Array.isArray(logos) ? logos : [];

  if (logoList.length === 0) {
    return (
      <div className="bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-8 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <svg
            className="mx-auto h-10 w-10 mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M4 6h16M4 12h16M4 18h16"
            />
          </svg>
          <p className="text-sm">Logo strip — no logos</p>
        </div>
      </div>
    );
  }

  return (
    <div
      style={{
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'center',
        gap: gap || '32px',
      }}
    >
      {logoList.map((url, i) => (
        <img
          key={i}
          src={url}
          alt=""
          className="h-10 object-contain"
          style={grayscale ? { filter: 'grayscale(100%)' } : undefined}
        />
      ))}
    </div>
  );
};
