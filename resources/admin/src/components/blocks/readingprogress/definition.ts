import type { BlockDefinition } from '@/types/blocks';

export const readingprogressDefinition: BlockDefinition = {
  type: 'readingprogress',
  category: 'interactive',
  label: 'Reading Progress',
  icon: 'BarChart2',
  defaultData: {
    style: 'top-bar',
    color: '',
    height: '3px',
  },
  allowsChildren: false,
};
