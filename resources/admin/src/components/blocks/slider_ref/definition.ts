import type { BlockDefinition } from '@/types/blocks';

export const sliderRefDefinition: BlockDefinition = {
  type: 'slider_ref',
  category: 'interactive',
  label: 'Slider',
  icon: 'GalleryHorizontalEnd',
  description: 'Embed a slider from the site library',
  defaultData: {
    sliderId: null,
  },
  allowsChildren: false,
};
