import type { BlockDefinition } from '@/types/blocks';

export const heroDefinition: BlockDefinition = {
  type: 'hero',
  category: 'content',
  label: 'Hero Section',
  icon: 'Layout',
  defaultData: {
    title: 'Hero Title',
    subtitle: '',
    backgroundImage: null,
    ctaText: '',
    ctaUrl: '',
  },
  allowsChildren: false,
};
