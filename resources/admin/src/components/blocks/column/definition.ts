import type { BlockDefinition } from '@/types/blocks';

export const columnDefinition: BlockDefinition = {
  type: 'column',
  category: 'layout',
  label: 'Column',
  icon: '▯',
  level: 'column',
  defaultData: {
    padding: '',
    vertical_align: 'start',
    background_color: '',
  },
  allowsChildren: true,
  maxChildren: 20,
};
