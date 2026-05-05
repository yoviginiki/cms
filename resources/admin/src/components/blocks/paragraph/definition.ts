import type { BlockDefinition } from '@/types/blocks';

export const paragraphDefinition: BlockDefinition = {
  type: 'paragraph',
  category: 'typography',
  label: 'Paragraph',
  icon: 'AlignLeft',
  description: 'A simple paragraph block with prose styling',
  defaultData: {
    content: '<p>Start writing...</p>',
  },
  allowsChildren: false,
  hasTypography: true,
};
