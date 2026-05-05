import type { BlockDefinition } from '@/types/blocks';

export const sharebuttonsDefinition: BlockDefinition = {
  type: 'sharebuttons',
  category: 'blog',
  label: 'Share Buttons',
  icon: 'Share2',
  defaultData: {
    platforms: ['twitter', 'facebook', 'linkedin', 'email', 'copy'],
    style: 'icons',
    showLabels: false,
  },
  allowsChildren: false,
};
