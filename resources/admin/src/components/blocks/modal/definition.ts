import type { BlockDefinition } from '@/types/blocks';

export const modalDefinition: BlockDefinition = {
  type: 'modal',
  category: 'interactive',
  label: 'Modal',
  icon: 'Maximize2',
  defaultData: {
    triggerText: 'Open',
    title: '',
    size: 'md',
  },
  allowsChildren: true,
};
