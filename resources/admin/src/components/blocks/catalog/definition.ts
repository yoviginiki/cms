import type { BlockDefinition } from '@/types/blocks';

export const catalogDefinition: BlockDefinition = {
  type: 'catalog',
  category: 'interactive',
  label: 'Catalog',
  icon: 'List',
  defaultData: {
    items: [
      {
        title: 'Item Title',
        subtitle: '',
        content: '<p>Description text</p>',
        contentSecondary: '',
        images: [],
      },
    ],
    headerLabels: ['no.', 'title', 'subtitle', ''],
    openFirst: true,
    imageHeight: '280px',
    imageFilter: 'grayscale',
    imageHoverReveal: true,
  },
  allowsChildren: false,
};
