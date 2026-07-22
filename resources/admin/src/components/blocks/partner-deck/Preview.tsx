import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { AppToolPreview } from '../appToolPreview';

export const PartnerDeckPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { eyebrow?: string; cards?: { title?: string }[] };
  const cards = Array.isArray(data.cards) ? data.cards : [];
  return (
    <AppToolPreview
      badge="Card deck"
      eyebrow={data.eyebrow}
      title={cards[0]?.title || 'Prompt card deck'}
      summary={`${cards.length || 0} cards · step through one at a time`}
      accent="#b4532a"
    />
  );
};
