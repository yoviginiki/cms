import type { BlockDefinition } from '@/types/blocks';

export const dividerDefinition: BlockDefinition = {
  type: 'divider',
  category: 'layout',
  label: 'Divider',
  icon: 'Minus',
  defaultData: {
    style: 'solid',
    color: '',
    thickness: '1px',
    width: '100%',
    alignment: 'center',
  },
  allowsChildren: false,
};
