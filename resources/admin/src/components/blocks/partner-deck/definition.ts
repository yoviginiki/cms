import type { BlockDefinition } from '@/types/blocks';

export const partnerDeckDefinition: BlockDefinition = {
  type: 'partner-deck',
  category: 'interactive',
  label: 'Prompt Card Deck',
  icon: 'Layers',
  description: 'A deck of title/body prompt cards the visitor steps through one at a time.',
  defaultData: {
    eyebrow: 'Invitation, never obligation',
    buttonLabel: 'Draw another',
    cards: [
      { title: 'Three-minute arrival', body: 'Sit facing each other. Share one easy breathing rhythm for three minutes. No fixing, no performance, no finish.' },
      { title: 'The touch map', body: 'Each person shows three kinds of touch: yes, maybe and not today. Switch roles. Curiosity matters more than agreement.' },
      { title: 'Pause is part of the dance', body: 'Choose a neutral pause word. When it is heard: stop, take three easy exhales, then choose together how to continue.' },
    ],
  },
  allowsChildren: false,
};
