import type { BlockDefinition } from '@/types/blocks';

export const runningtextDefinition: BlockDefinition = {
  type: 'runningtext',
  category: 'typography',
  label: 'Running Text',
  icon: 'Columns2',
  description: 'Multi-column flowing text layout',
  defaultData: {
    content: '',
    columns: 2,
    columnGap: '40px',
    columnRule: false,
  },
  allowsChildren: false,
  hasTypography: true,
};
