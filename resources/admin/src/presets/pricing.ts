import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

function pricingCol(plan: string, price: string, features: string[], highlighted: boolean, order: number): BlockData {
  return {
    id: uid(),
    type: 'column',
    level: 'column',
    order,
    data: { padding: '24px', vertical_align: 'start' },
    children: [{
      id: uid(),
      type: 'pricingcard',
      level: 'module',
      order: 0,
      data: {
        planName: plan,
        price,
        period: 'month',
        features: features.map(f => ({ text: f, included: true })),
        ctaText: 'Choose Plan',
        ctaUrl: '#',
        highlighted,
        badge: highlighted ? 'Popular' : '',
      },
      children: [],
    }],
  };
}

export const pricingPreset: PresetDefinition = {
  type: 'preset_pricing',
  label: 'Pricing Table',
  icon: 'CreditCard',
  description: '3-column pricing cards with CTA',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '1200px' },
    children: [
      {
        id: uid(),
        type: 'heading',
        level: 'module',
        order: 0,
        data: { text: 'Simple, Transparent Pricing', level: 'h2', fontSize: '2rem' },
        children: [],
      },
      {
        id: uid(),
        type: 'row',
        level: 'row',
        order: 1,
        data: { layout: '1/3+1/3+1/3', gap: '24px' },
        children: [
          pricingCol('Starter', '$9', ['5 Pages', '1 GB Storage', 'Email Support'], false, 0),
          pricingCol('Pro', '$29', ['Unlimited Pages', '10 GB Storage', 'Priority Support', 'Custom Domain'], true, 1),
          pricingCol('Enterprise', '$99', ['Everything in Pro', 'White Label', 'API Access', 'Dedicated Manager'], false, 2),
        ],
      },
    ],
  }),
};
