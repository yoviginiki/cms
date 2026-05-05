import type { BlockDefinition } from '@/types/blocks';

export const postcardDefinition: BlockDefinition = {
  type: 'postcard',
  category: 'blog',
  label: 'Post Card',
  icon: 'FileText',
  defaultData: {
    postId: '',
    style: 'vertical',
    showExcerpt: true,
    showDate: true,
    showCategory: true,
  },
  allowsChildren: false,
};
