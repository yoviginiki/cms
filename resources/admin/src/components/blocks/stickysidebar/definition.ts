import type { BlockDefinition } from '@/types/blocks';

export const stickysidebarDefinition: BlockDefinition = {
  type: 'stickysidebar',
  category: 'layout',
  label: 'Sticky Sidebar',
  icon: 'PanelLeft',
  defaultData: {
    sidebarSide: 'right',
    sidebarWidth: '300px',
    gap: '32px',
    stickyOffset: '80px',
  },
  allowsChildren: true,
};
