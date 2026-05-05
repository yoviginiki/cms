import type { BlockDefinition } from '@/types/blocks';

export const galleryDefinition: BlockDefinition = {
  type: 'gallery',
  category: 'media',
  label: 'Gallery',
  icon: 'LayoutGrid',
  defaultData: {
    images: [],
    layout: 'grid',
    columns: 3,
    gap: '8px',
  },
  allowsChildren: false,
};
