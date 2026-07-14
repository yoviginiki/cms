import type { BlockDefinition } from '@/types/blocks';

export const recordImageDefinition: BlockDefinition = {
  type: 'record-image',
  category: 'dynamic',
  label: 'Record Image',
  icon: 'Image',
  description: 'Displays an image field of the current record',
  level: 'module',
  defaultData: {
    field: '',
    aspectRatio: 'auto',
    objectFit: 'cover',
  },
  allowsChildren: false,
};
