import type { BlockDefinition } from '@/types/blocks';

export const tooltipDefinition: BlockDefinition = {
  type: 'tooltip',
  category: 'interactive',
  label: 'Tooltip',
  icon: 'MessageCircle',
  defaultData: {
    triggerText: 'Hover me',
    tooltipText: 'Tooltip content',
    position: 'top',
  },
  allowsChildren: false,
};
