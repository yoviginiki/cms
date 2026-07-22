import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { AppToolPreview } from '../appToolPreview';

export const BreathingPacerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { eyebrow?: string; title?: string; phases?: unknown[]; defaultRounds?: number };
  const phases = Array.isArray(data.phases) ? data.phases.length : 4;
  return (
    <AppToolPreview
      badge="Breathing"
      eyebrow={data.eyebrow}
      title={data.title || 'Breathing pacer'}
      summary={`${phases} phase${phases === 1 ? '' : 's'} · ${data.defaultRounds ?? 5} rounds · animated orb + cues`}
      accent="#b4532a"
    />
  );
};
