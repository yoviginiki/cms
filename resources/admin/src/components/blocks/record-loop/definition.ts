import type { BlockDefinition } from '@/types/blocks';

export const recordLoopDefinition: BlockDefinition = {
  type: 'record-loop',
  category: 'dynamic',
  label: 'Record Loop',
  icon: 'Database',
  description: 'Lists published records from a collection',
  level: 'module',
  defaultData: {
    collectionId: null,
    sourceMode: 'auto',    // auto, children, related (record-template context)
    relatedCollectionId: null,
    relatedFieldKey: null,
    layout: 'cards',       // cards, list, grid
    columns: 3,
    limit: 12,
    sortField: '',
    sortDirection: 'desc',
    filterField: null,
    filterValue: '',
    showImage: true,
    imageField: null,
    cardFields: [],
    linkToRecord: true,
    gap: '1.5rem',
  },
  allowsChildren: false,
};
