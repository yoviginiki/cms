import type { BlockDefinition } from '@/types/blocks';

export const searchBoxDefinition: BlockDefinition = {
  type: 'search-box',
  category: 'dynamic',
  label: 'Search Box',
  icon: 'Search',
  description: 'Search input for a collection (client-side island)',
  level: 'module',
  defaultData: {
    collectionId: null,
    placeholder: '',
  },
  allowsChildren: false,
};
