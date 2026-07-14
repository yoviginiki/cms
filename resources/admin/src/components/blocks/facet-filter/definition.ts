import type { BlockDefinition } from '@/types/blocks';

export const facetFilterDefinition: BlockDefinition = {
  type: 'facet-filter',
  category: 'dynamic',
  label: 'Facet Filter',
  icon: 'Filter',
  description: 'Filter checkboxes/dropdowns for a collection\'s facetable fields',
  level: 'module',
  defaultData: {
    collectionId: null,
    fields: [],
    style: 'checkbox',     // checkbox, dropdown
  },
  allowsChildren: false,
};
