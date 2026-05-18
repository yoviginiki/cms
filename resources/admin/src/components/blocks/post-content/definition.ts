import type { BlockDefinition } from '@/types/blocks';

export const postContentDefinition: BlockDefinition = {
  type: 'post-content',
  category: 'dynamic',
  label: 'Post Content',
  icon: 'FileText',
  description: 'Renders the full post content (blocks)',
  level: 'module',
  defaultData: {},
  allowsChildren: false,
};
