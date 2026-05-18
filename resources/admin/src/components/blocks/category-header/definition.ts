import type { BlockDefinition } from '@/types/blocks';

export const categoryHeaderDefinition: BlockDefinition = {
  type: 'category-header',
  category: 'dynamic',
  label: 'Category Header',
  icon: 'FolderOpen',
  description: 'Displays category name and description',
  level: 'module',
  defaultData: {
    showDescription: true,
    showPostCount: true,
    titleTag: 'h1',
    titleSize: '',
    titleColor: '',
    textAlign: 'center',
  },
  allowsChildren: false,
};
