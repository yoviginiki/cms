import type { BlockDefinition } from '@/types/blocks';

export const shapeDefinition: BlockDefinition = {
  type: 'shape',
  category: 'media',
  label: 'Shape',
  icon: 'Square',
  description: 'Solid rectangle / bar (slider layer primitive)',
  defaultData: {
    color: '#E63B2E',
  },
  allowsChildren: false,
};
