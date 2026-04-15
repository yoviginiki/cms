import type { BlockDefinition } from '@/types/blocks';

export const columnsDefinition: BlockDefinition = {
  type: 'columns',
  category: 'layout',
  label: 'Columns',
  icon: 'Columns',
  defaultData: {
    columnCount: 2,
    gap: 'medium',
  },
  allowsChildren: true,
};
