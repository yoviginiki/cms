import type { BlockDefinition } from '@/types/blocks';

export const pelvicTrainerDefinition: BlockDefinition = {
  type: 'pelvic-trainer',
  category: 'interactive',
  label: 'Guided Coordination',
  icon: 'Activity',
  description: 'A phase-by-phase guided coordination trainer that cycles cued steps for a set number of rounds.',
  defaultData: {
    eyebrow: 'Guided coordination · 6 rounds',
    rounds: 6,
    phases: [
      { label: 'Arrive', cue: 'Feel the weight of the pelvis. Do nothing yet.', seconds: 8 },
      { label: 'Inhale & widen', cue: 'Let the lower ribs, belly and pelvic floor receive the breath.', seconds: 5 },
      { label: 'Gentle lift', cue: 'Lift at about 30% effort — no glute or abdominal squeeze.', seconds: 3 },
      { label: 'Release fully', cue: 'Let go for longer than you lifted. Notice the difference.', seconds: 6 },
    ],
  },
  allowsChildren: false,
};
