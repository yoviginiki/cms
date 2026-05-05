import type { BlockDefinition } from '@/types/blocks';

export const tableDefinition: BlockDefinition = {
  type: 'table',
  category: 'data',
  label: 'Table',
  icon: 'Table2',
  defaultData: {
    headers: ['Header 1', 'Header 2', 'Header 3'],
    rows: [['Cell', 'Cell', 'Cell']],
    striped: true,
    compact: false,
  },
  allowsChildren: false,
};
