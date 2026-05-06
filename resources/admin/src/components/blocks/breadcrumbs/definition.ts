import type { BlockDefinition } from '@/types/blocks';

export const breadcrumbsDefinition: BlockDefinition = {
  type: 'breadcrumbs',
  category: 'navigation',
  label: 'Breadcrumbs',
  icon: 'ChevronRight',
  description: 'Auto-generated breadcrumb trail based on page hierarchy',
  defaultData: {
    separator: '/',
    showHome: true,
    homeLabel: 'Home',
    showCurrent: true,
    schema: true,
  },
  allowsChildren: false,
};
