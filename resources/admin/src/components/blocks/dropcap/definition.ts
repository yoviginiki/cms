import type { BlockDefinition } from '@/types/blocks';

export const dropcapDefinition: BlockDefinition = {
  type: 'dropcap',
  category: 'typography',
  label: 'Drop Cap',
  icon: 'ALargeSmall',
  description: 'Paragraph with a large decorative first letter',
  defaultData: {
    content: '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    capSize: 3,
    capColor: null,
  },
  allowsChildren: false,
  hasTypography: true,
};
