import type { BlockDefinition } from '@/types/blocks';

export const authorboxDefinition: BlockDefinition = {
  type: 'authorbox',
  category: 'blog',
  label: 'Author Box',
  icon: 'UserCircle',
  defaultData: {
    showAvatar: true,
    showBio: true,
    showSocialLinks: false,
    layout: 'horizontal',
  },
  allowsChildren: false,
};
