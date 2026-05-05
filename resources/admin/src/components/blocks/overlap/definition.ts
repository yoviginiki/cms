import type { BlockDefinition } from '@/types/blocks';

export const overlapDefinition: BlockDefinition = {
  type: 'overlap',
  category: 'layout',
  label: 'Overlap',
  icon: 'Layers',
  defaultData: {
    offsetY: '-40px',
    offsetX: '0',
    zIndex: 1,
  },
  allowsChildren: true,
};
