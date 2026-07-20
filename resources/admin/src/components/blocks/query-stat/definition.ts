import type { BlockDefinition } from '@/types/blocks';

export const queryStatDefinition: BlockDefinition = {
  type: 'query-stat',
  category: 'dynamic',
  label: 'Query Stat',
  icon: 'Sigma',
  description: 'One number from a saved query (count, sum, average…)',
  level: 'module',
  defaultData: {
    queryId: null,
    label: '',
    prefix: '',
    suffix: '',
    size: 'lg',        // sm, md, lg, xl
    textAlign: 'left',
  },
  allowsChildren: false,
};
