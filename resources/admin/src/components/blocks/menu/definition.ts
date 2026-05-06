import type { BlockDefinition } from '@/types/blocks';

export const menuDefinition: BlockDefinition = {
  type: 'menu',
  category: 'navigation',
  label: 'Menu',
  icon: 'Menu',
  description: 'Site navigation menu from your configured menus',
  defaultData: {
    menuId: '',
    style: 'horizontal',
    showLogo: false,
    sticky: false,
    mobileBreakpoint: 768,
  },
  allowsChildren: false,
};
