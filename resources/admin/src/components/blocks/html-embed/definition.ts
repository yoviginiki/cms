import type { BlockDefinition } from '@/types/blocks';

export const htmlEmbedDefinition: BlockDefinition = {
  type: 'html-embed',
  category: 'content',
  label: 'HTML Embed',
  icon: '</>',
  defaultData: {
    html: '',
  },
  allowsChildren: false,
};
