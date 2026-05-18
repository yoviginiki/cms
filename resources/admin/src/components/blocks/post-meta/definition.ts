import type { BlockDefinition } from '@/types/blocks';

export const postMetaDefinition: BlockDefinition = {
  type: 'post-meta',
  category: 'dynamic',
  label: 'Post Meta',
  icon: 'Info',
  description: 'Shows date, author, and category as a meta line',
  level: 'module',
  defaultData: {
    showDate: true,
    showAuthor: true,
    showCategory: true,
    separator: '·',
    textAlign: '',
  },
  allowsChildren: false,
};
