import type { BlockDefinition } from '@/types/blocks';

export const backToTopDefinition: BlockDefinition = {
  type: 'back-to-top',
  category: 'navigation',
  label: 'Back to Top',
  icon: 'ArrowUpToLine',
  defaultData: { label: 'Back to top', style: 'link', align: 'right' },
  allowsChildren: false,
};
