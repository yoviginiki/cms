import type { BlockDefinition } from '@/types/blocks';

export const pricingtableDefinition: BlockDefinition = {
  type: 'pricingtable',
  category: 'data',
  label: 'Pricing Table',
  icon: 'CreditCard',
  defaultData: {
    plans: [
      {
        name: 'Basic',
        price: '$9',
        period: '/mo',
        features: ['Feature 1', 'Feature 2'],
        ctaText: 'Choose',
        ctaUrl: '#',
        highlighted: false,
      },
    ],
    columns: 3,
    textShadow: '',
  },
  allowsChildren: false,
};
