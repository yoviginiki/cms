import type { BlockDefinition } from '@/types/blocks';

export const fieldValueDefinition: BlockDefinition = {
  type: 'field-value',
  category: 'dynamic',
  label: 'Field Value',
  icon: 'ALargeSmall',
  description: 'Displays any schema field of the current record',
  level: 'module',
  defaultData: {
    field: '',
    showLabel: false,
    labelText: '',
    emptyText: '',
    textAlign: 'left',
    fontSize: '',
  },
  allowsChildren: false,
};
