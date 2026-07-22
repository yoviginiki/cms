import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { AppToolPreview } from '../appToolPreview';

export const MeditationTimerPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { eyebrow?: string; title?: string; presets?: number[]; showJourneys?: boolean; journeys?: Record<string, number[]> };
  const presets = Array.isArray(data.presets) ? data.presets.length : 6;
  const journeys = data.showJourneys !== false ? Object.keys(data.journeys || {}).length : 0;
  return (
    <AppToolPreview
      badge="Meditation"
      eyebrow={data.eyebrow}
      title={data.title || 'Meditation timer'}
      summary={`${presets} presets · soft bell${journeys ? ` · ${journeys} journeys` : ''}`}
    />
  );
};
