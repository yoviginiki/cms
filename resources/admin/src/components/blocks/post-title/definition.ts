import type { BlockDefinition } from '@/types/blocks';

export const postTitleDefinition: BlockDefinition = {
  type: 'post-title',
  category: 'dynamic',
  label: 'Post Title',
  icon: 'Type',
  description: 'Displays the post title dynamically',
  level: 'module',
  defaultData: {
    tag: 'h1',
    fontSize: '',
    fontWeight: '',
    color: '',
    textAlign: '',
    textShadow: '',
  },
  allowsChildren: false,
};
