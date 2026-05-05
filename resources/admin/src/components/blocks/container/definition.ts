import type { BlockDefinition } from '@/types/blocks';

export const containerDefinition: BlockDefinition = {
  type: 'container',
  category: 'layout',
  label: 'Container',
  icon: 'Square',
  defaultData: {
    maxWidth: '1200',
    centered: true,
  },
  allowsChildren: true,
};
