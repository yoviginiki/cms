import type { BlockDefinition } from '@/types/blocks';

export const globalRefDefinition: BlockDefinition = {
  type: 'global_ref',
  category: 'interactive',
  label: 'Global Section',
  icon: 'Boxes',
  description: 'Embed a reusable section from the library — edit once, updates everywhere',
  defaultData: {
    sectionId: null,
  },
  allowsChildren: false,
};
