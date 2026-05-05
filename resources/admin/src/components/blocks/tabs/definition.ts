import type { BlockDefinition } from '@/types/blocks';

export const tabsDefinition: BlockDefinition = {
  type: 'tabs',
  category: 'interactive',
  label: 'Tabs',
  icon: '📑',
  defaultData: {
    tab_labels: ['Tab 1', 'Tab 2'],
    style: 'underline',
    alignment: 'left',
  },
  allowsChildren: true,
};
