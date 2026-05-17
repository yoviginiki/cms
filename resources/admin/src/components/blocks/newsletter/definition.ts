import type { BlockDefinition } from '@/types/blocks';

export const newsletterDefinition: BlockDefinition = {
  type: 'newsletter',
  category: 'blog',
  label: 'Newsletter',
  icon: 'Mail',
  defaultData: {
    heading: 'Subscribe',
    description: 'Get updates',
    buttonText: 'Subscribe',
    endpoint: '',
    style: 'inline',
    headingTextShadow: '',
  },
  allowsChildren: false,
};
