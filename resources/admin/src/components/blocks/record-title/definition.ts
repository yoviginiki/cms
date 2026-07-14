import type { BlockDefinition } from '@/types/blocks';

export const recordTitleDefinition: BlockDefinition = {
  type: 'record-title',
  category: 'dynamic',
  label: 'Record Title',
  icon: 'Heading',
  description: 'Displays the collection record title dynamically',
  level: 'module',
  defaultData: {
    tag: 'h1',
    fontSize: '',
    textAlign: 'left',
    color: '',
  },
  allowsChildren: false,
};
