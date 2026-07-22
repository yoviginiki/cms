import type { BlockDefinition } from '@/types/blocks';

export const breathingPacerDefinition: BlockDefinition = {
  type: 'breathing-pacer',
  category: 'interactive',
  label: 'Breathing Pacer',
  icon: 'Wind',
  description: 'Animated breathing orb with per-phase durations, rounds and optional cues.',
  defaultData: {
    eyebrow: 'Interactive practice',
    title: 'Box breathing pacer',
    soundLabel: 'Gentle cues',
    soundDefault: true,
    advancedAt: 20,
    defaultRounds: 5,
    roundOptions: [3, 5, 8],
    phases: [
      { label: 'Inhale', value: 3, min: 3, max: 60, step: 1, locked: false },
      { label: 'Hold gently', value: 3, min: 3, max: 60, step: 1, locked: true },
      { label: 'Exhale', value: 3, min: 3, max: 60, step: 1, locked: true },
      { label: 'Rest empty', value: 3, min: 3, max: 60, step: 1, locked: true },
    ],
  },
  allowsChildren: false,
};
