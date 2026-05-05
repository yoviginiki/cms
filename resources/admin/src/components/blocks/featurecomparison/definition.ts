import type { BlockDefinition } from '@/types/blocks';

export const featurecomparisonDefinition: BlockDefinition = {
  type: 'featurecomparison',
  category: 'commerce',
  label: 'Feature Comparison',
  icon: 'Table',
  defaultData: {
    plans: [
      { name: 'Basic', price: '$9' },
      { name: 'Pro', price: '$29' },
    ],
    features: [
      { name: 'Feature 1', values: [true, true] },
      { name: 'Feature 2', values: [false, true] },
    ],
  },
  allowsChildren: false,
};
