import type { BlockDefinition } from '@/types/blocks';

export const socialembedDefinition: BlockDefinition = {
  type: 'socialembed',
  category: 'embed',
  label: 'Social Embed',
  icon: 'Globe',
  defaultData: {
    url: '',
    platform: 'auto',
  },
  allowsChildren: false,
};
