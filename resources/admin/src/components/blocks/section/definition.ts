import type { BlockDefinition } from '@/types/blocks';

export const sectionDefinition: BlockDefinition = {
  type: 'section',
  category: 'layout',
  label: 'Section',
  icon: '📦',
  defaultData: {
    background_color: '',
    background_image: '',
    padding: 'md',
    max_width: '1200px',
    anchor_id: '',
    tag: 'section',
    fullWidth: false,
    minHeight: '',
    verticalAlign: 'start',
  },
  allowsChildren: true,
};
