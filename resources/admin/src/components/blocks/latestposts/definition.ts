import type { BlockDefinition } from '@/types/blocks';

export const latestpostsDefinition: BlockDefinition = {
  type: 'latestposts',
  category: 'blog',
  label: 'Latest Posts',
  icon: 'Clock',
  defaultData: {
    limit: 5,
    layout: 'list',
    showImage: true,
  },
  allowsChildren: false,
};
