import type { BlockDefinition } from '@/types/blocks';

export const archivePaginationDefinition: BlockDefinition = {
  type: 'archive-pagination',
  category: 'dynamic',
  label: 'Pagination',
  icon: 'ChevronsLeftRight',
  description: 'Page navigation for archive listings',
  level: 'module',
  defaultData: {
    style: 'numbered',  // numbered, simple, load-more
    align: 'center',
  },
  allowsChildren: false,
};
