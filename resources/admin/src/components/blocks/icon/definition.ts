import type { BlockDefinition } from '@/types/blocks';

export const iconDefinition: BlockDefinition = {
  type: 'icon',
  category: 'media',
  label: 'Icon',
  icon: 'Smile',
  defaultData: {
    name: 'star',
    size: 'md',
    color: '',
    background: 'none',
    backgroundColor: '',
  },
  allowsChildren: false,
};
