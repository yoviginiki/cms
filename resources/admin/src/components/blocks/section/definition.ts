import type { BlockDefinition } from '@/types/blocks';

export const sectionDefinition: BlockDefinition = {
  type: 'section',
  category: 'layout',
  label: 'Section',
  icon: 'PanelTop',
  level: 'section',
  defaultData: {
    background_color: '',
    background_image: '',
    padding_top: '40px',
    padding_bottom: '40px',
    max_width: '1200px',
    anchor_id: '',
    scene: 'fade-through',
  },
  allowsChildren: true,
  maxChildren: 20,
};
