import type { BlockDefinition } from '@/types/blocks';

export const spacerDefinition: BlockDefinition = {
  type: 'spacer',
  category: 'layout',
  label: 'Spacer',
  icon: '↕',
  defaultData: {
    height: 'md',
  },
  allowsChildren: false,
};
