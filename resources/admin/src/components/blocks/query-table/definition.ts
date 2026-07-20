import type { BlockDefinition } from '@/types/blocks';

export const queryTableDefinition: BlockDefinition = {
  type: 'query-table',
  category: 'dynamic',
  label: 'Query Table',
  icon: 'Table',
  description: 'Renders a saved query’s rows as a static table at publish',
  level: 'module',
  defaultData: {
    queryId: null,
    showHeader: true,
    maxRows: 20,
    striped: true,
  },
  allowsChildren: false,
};
