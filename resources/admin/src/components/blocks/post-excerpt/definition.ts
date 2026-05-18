import type { BlockDefinition } from '@/types/blocks';

export const postExcerptDefinition: BlockDefinition = {
  type: 'post-excerpt',
  category: 'dynamic',
  label: 'Post Excerpt',
  icon: 'AlignLeft',
  description: 'Shows the post excerpt text',
  level: 'module',
  defaultData: {
    fontSize: '',
    color: '',
    textAlign: '',
    maxLines: 0,
  },
  allowsChildren: false,
};
