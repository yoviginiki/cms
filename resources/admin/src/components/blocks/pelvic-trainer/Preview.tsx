import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { AppToolPreview } from '../appToolPreview';

export const PelvicTrainerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { eyebrow?: string; phases?: { label?: string }[]; rounds?: number };
  const phases = Array.isArray(data.phases) ? data.phases : [];
  const first = phases[0]?.label || 'Arrive';
  return (
    <AppToolPreview
      badge="Guided"
      eyebrow={data.eyebrow}
      title={first}
      summary={`${phases.length || 4} phases · ${data.rounds ?? 6} rounds · animated visual`}
    />
  );
};
