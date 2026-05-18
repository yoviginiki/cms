import type { BlockDefinition } from '@/types/blocks';

export const statsDefinition: BlockDefinition = {
  type: 'stats',
  category: 'data',
  label: 'Stats',
  icon: 'TrendingUp',
  defaultData: {
    items: [{ value: '100', label: 'Users', prefix: '', suffix: '+' }],
    columns: 3,
    textShadow: '',
  },
  allowsChildren: false,
};
