import type { BlockDefinition } from '@/types/blocks';

export const captionDefinition: BlockDefinition = {
  type: 'caption',
  category: 'typography',
  label: 'Caption',
  icon: 'Subtitles',
  description: 'Small muted caption text for figures and images',
  defaultData: {
    text: '',
    prefix: 'Fig.',
  },
  allowsChildren: false,
  hasTypography: true,
};
