import type { BlockDefinition } from '@/types/blocks';

export const imagecaptionDefinition: BlockDefinition = {
  type: 'imagecaption',
  category: 'media',
  label: 'Image with Caption',
  icon: 'ImagePlus',
  defaultData: {
    src: '',
    alt: '',
    caption: '',
    captionPosition: 'below',
  },
  allowsChildren: false,
};
