import type { BlockDefinition } from '@/types/blocks';

export const sidenoteDefinition: BlockDefinition = {
  type: 'sidenote',
  category: 'typography',
  label: 'Side Note',
  icon: 'PanelRight',
  description: 'Marginal note floated to one side',
  defaultData: {
    content: '',
    side: 'right',
  },
  allowsChildren: false,
  hasTypography: true,
};
