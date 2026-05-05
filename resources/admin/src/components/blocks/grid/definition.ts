import type { BlockDefinition } from '@/types/blocks';

export const gridDefinition: BlockDefinition = {
  type: 'grid',
  category: 'layout',
  label: 'Grid',
  icon: 'LayoutGrid',
  defaultData: {
    templateColumns: '1fr 1fr',
    templateRows: 'auto',
    gap: '16px',
    autoFlow: 'row',
  },
  allowsChildren: true,
};
