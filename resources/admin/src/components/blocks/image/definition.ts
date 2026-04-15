import type { BlockDefinition } from '@/types/blocks';

export const imageDefinition: BlockDefinition = {
  type: 'image',
  category: 'media',
  label: 'Image',
  icon: 'Image',
  defaultData: {
    assetId: null,
    url: '',
    alt: '',
    caption: '',
    size: 'full',
  },
  allowsChildren: false,
};
