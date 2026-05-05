import type { BlockDefinition } from '@/types/blocks';

export const textdividerDefinition: BlockDefinition = {
  type: 'textdivider',
  category: 'typography',
  label: 'Text Divider',
  icon: 'Minus',
  description: 'Decorative divider between text sections',
  defaultData: {
    style: 'line',
    customSymbol: '',
    width: 'half',
  },
  allowsChildren: false,
  hasTypography: false,
};
