import type { BlockDefinition } from '@/types/blocks';

export const meditationTimerDefinition: BlockDefinition = {
  type: 'meditation-timer',
  category: 'interactive',
  label: 'Meditation Timer',
  icon: 'Timer',
  description: 'Progress-ring meditation timer with presets, a soft bell and optional day journeys.',
  defaultData: {
    eyebrow: 'Practise now',
    title: 'Zen meditation timer',
    presets: [5, 10, 15, 20, 30, 45],
    defaultMinutes: 5,
    showJourneys: true,
    storeKey: 'rr-med',
    journeys: {
      '3-day opening': [5, 10, 15],
      '5-day steady': [5, 8, 12, 15, 20],
      '5-day deepening': [10, 15, 20, 25, 30],
    },
  },
  allowsChildren: false,
};
