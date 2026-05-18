import type { BlockDefinition } from '@/types/blocks';

export const postNavigationDefinition: BlockDefinition = {
  type: 'post-navigation',
  category: 'dynamic',
  label: 'Post Navigation',
  icon: 'ArrowLeftRight',
  description: 'Shows previous and next post links',
  level: 'module',
  defaultData: {
    showLabels: true,
    style: 'minimal',
  },
  allowsChildren: false,
};
