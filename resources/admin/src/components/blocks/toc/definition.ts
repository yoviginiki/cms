import type { BlockDefinition } from '@/types/blocks';

export const tocDefinition: BlockDefinition = {
  type: 'toc',
  category: 'interactive',
  label: 'Table of Contents',
  icon: 'ListTree',
  defaultData: {
    maxDepth: 3,
    style: 'inline',
    sticky: false,
  },
  allowsChildren: false,
};
