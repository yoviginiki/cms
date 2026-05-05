import type { BlockDefinition } from '@/types/blocks';

export const beforeafterDefinition: BlockDefinition = {
  type: 'beforeafter',
  category: 'media',
  label: 'Before / After',
  icon: 'SplitSquareHorizontal',
  defaultData: {
    beforeSrc: '',
    afterSrc: '',
    beforeLabel: 'Before',
    afterLabel: 'After',
    initialPosition: 50,
  },
  allowsChildren: false,
};
