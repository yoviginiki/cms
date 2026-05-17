import type { BlockDefinition } from '@/types/blocks';

export const pricingcardDefinition: BlockDefinition = {
  type: 'pricingcard',
  category: 'commerce',
  label: 'Pricing Card',
  icon: 'CreditCard',
  defaultData: {
    planName: 'Pro',
    price: '$29',
    period: 'month',
    features: [
      { text: 'Feature 1', included: true },
      { text: 'Feature 2', included: true },
      { text: 'Feature 3', included: false },
    ],
    ctaText: 'Get started',
    ctaUrl: '#',
    highlighted: false,
    badge: '',
    textShadow: '',
  },
  allowsChildren: false,
};
