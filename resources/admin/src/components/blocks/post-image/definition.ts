import type { BlockDefinition } from '@/types/blocks';

export const postImageDefinition: BlockDefinition = {
  type: 'post-image',
  category: 'dynamic',
  label: 'Post Image',
  icon: 'Image',
  description: 'Shows featured image or thumbnail from post data',
  level: 'module',
  defaultData: {
    size: 'full',
    aspectRatio: '',
    borderRadius: '',
    objectFit: 'cover',
  },
  allowsChildren: false,
};
