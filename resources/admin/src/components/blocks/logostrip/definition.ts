import type { BlockDefinition } from '@/types/blocks';

export const logostripDefinition: BlockDefinition = {
  type: 'logostrip',
  category: 'media',
  label: 'Logo Strip',
  icon: 'GalleryHorizontalEnd',
  defaultData: {
    logos: [],
    grayscale: true,
    columns: 4,
    gap: '32px',
  },
  allowsChildren: false,
};
