import type { BlockDefinition } from '@/types/blocks';

export const menuDefinition: BlockDefinition = {
  type: 'menu',
  category: 'navigation',
  label: 'Menu',
  icon: 'Menu',
  description: 'Site navigation menu — use a system menu or create custom links',
  defaultData: {
    // Source: 'system' (picks from configured menus) or 'custom' (inline items)
    source: 'system',
    menuId: '',
    // Custom inline items (when source='custom')
    customItems: [],
    // Layout
    style: 'horizontal',
    showLogo: false,
    sticky: false,
    mobileBreakpoint: 768,
    // Hamburger
    hamburgerIcon: 'bars',
    // Styling
    bgColor: '',
    textColor: '',
    hoverColor: '',
    activeColor: '',
    borderColor: '',
    fontSize: '',
    fontWeight: '',
    padding: '',
    itemGap: '',
    borderRadius: '',
    logoSize: '',
  },
  allowsChildren: false,
};
