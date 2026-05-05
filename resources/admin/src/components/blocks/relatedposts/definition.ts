import type { BlockDefinition } from '@/types/blocks';

export const relatedpostsDefinition: BlockDefinition = {
  type: 'relatedposts',
  category: 'blog',
  label: 'Related Posts',
  icon: 'Link',
  defaultData: {
    limit: 3,
    basedOn: 'category',
  },
  allowsChildren: false,
};
