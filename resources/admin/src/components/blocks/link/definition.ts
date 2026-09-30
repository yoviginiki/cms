import type { BlockDefinition } from '@/types/blocks';

export const linkDefinition: BlockDefinition = {
  type: 'link',
  category: 'content',
  label: 'Link',
  icon: 'Link',
  defaultData: {
    text: 'Link',
    linkType: 'custom',
    linkUrl: '',
    linkTarget: '_self',
    icon: 'auto',
    iconPosition: 'left',
    brandColor: false,
    color: '',
    fontSize: '',
    fontWeight: '',
    underline: 'hover',
    align: 'left',
  },
  allowsChildren: false,
};
