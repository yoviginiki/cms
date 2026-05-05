import type { BlockDefinition } from '@/types/blocks';

export const ctabannerDefinition: BlockDefinition = {
  type: 'ctabanner',
  category: 'interactive',
  label: 'CTA Banner',
  icon: 'Megaphone',
  hasTypography: true,
  defaultData: {
    heading: 'Ready to get started?',
    text: '',
    buttonText: 'Get started',
    buttonUrl: '#',
    backgroundStyle: 'solid',
    backgroundColor: '',
    backgroundImage: '',
  },
  allowsChildren: false,
};
