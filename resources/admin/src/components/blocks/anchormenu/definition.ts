import type { BlockDefinition } from '@/types/blocks';

export const anchormenuDefinition: BlockDefinition = {
  type: 'anchormenu',
  category: 'navigation',
  label: 'Anchor Menu',
  icon: 'Anchor',
  description: 'One-page navigation with smooth scroll to sections',
  defaultData: {
    items: [
      { label: 'Section 1', anchor: '#section-1' },
      { label: 'Section 2', anchor: '#section-2' },
      { label: 'Section 3', anchor: '#section-3' },
    ],
    style: 'horizontal',
    sticky: true,
    smooth: true,
    offset: 80,
    activeHighlight: true,
  },
  allowsChildren: false,
};
