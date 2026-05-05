import type { BlockDefinition } from '@/types/blocks';

export const featuregridDefinition: BlockDefinition = {
  type: 'featuregrid',
  category: 'data',
  label: 'Feature Grid',
  icon: 'LayoutGrid',
  defaultData: {
    items: [{ icon: 'star', title: 'Feature', description: 'Description' }],
    columns: 3,
    style: 'icon-top',
  },
  allowsChildren: false,
};
