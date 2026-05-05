import type { BlockDefinition } from '@/types/blocks';

export const fullbleedDefinition: BlockDefinition = {
  type: 'fullbleed',
  category: 'media',
  label: 'Full Bleed Image',
  icon: 'Expand',
  hasTypography: true,
  defaultData: {
    src: '',
    alt: '',
    overlayText: '',
    overlayPosition: 'center',
    scrimOpacity: 0.4,
    minHeight: '60vh',
  },
  allowsChildren: false,
};
