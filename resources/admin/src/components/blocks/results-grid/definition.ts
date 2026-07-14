import type { BlockDefinition } from '@/types/blocks';

export const resultsGridDefinition: BlockDefinition = {
  type: 'results-grid',
  category: 'dynamic',
  label: 'Results Grid',
  icon: 'Grid3x3',
  description: 'Client-side search results for a collection',
  level: 'module',
  defaultData: {
    collectionId: null,
    columns: 3,
    showImage: true,
    cardFields: [],
    emptyText: '',
  },
  allowsChildren: false,
};
