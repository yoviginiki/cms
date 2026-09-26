import type { BlockDefinition } from '@/types/blocks';

export const copyrightDefinition: BlockDefinition = {
  type: 'copyright',
  category: 'navigation',
  label: 'Copyright',
  icon: 'Copyright',
  defaultData: { text: '© {year} {site}. All rights reserved.', startYear: null, align: 'left' },
  allowsChildren: false,
};
