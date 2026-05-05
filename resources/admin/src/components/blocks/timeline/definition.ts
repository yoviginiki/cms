import type { BlockDefinition } from '@/types/blocks';

export const timelineDefinition: BlockDefinition = {
  type: 'timeline',
  category: 'data',
  label: 'Timeline',
  icon: 'Clock',
  defaultData: {
    items: [{ date: '2024', title: 'Event', description: 'Description' }],
    layout: 'left',
    lineStyle: 'solid',
  },
  allowsChildren: false,
};
