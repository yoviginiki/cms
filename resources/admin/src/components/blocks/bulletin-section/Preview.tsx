import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const BulletinSectionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const title = (block.data.title as string) || '';

  return (
    <section className="bulletin-section py-2">
      {title ? (
        <h2 className="text-lg font-semibold text-base-content">{title}</h2>
      ) : (
        <h2 className="text-lg font-semibold text-base-content/30 italic">Untitled section</h2>
      )}
    </section>
  );
};
