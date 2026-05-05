import type { BlockDefinition } from '@/types/blocks';

export const paywallDefinition: BlockDefinition = {
  type: 'paywall',
  category: 'commerce',
  label: 'Paywall',
  icon: 'Lock',
  defaultData: {
    previewLines: 3,
    blurIntensity: 8,
    heading: 'Subscribe to continue reading',
    ctaText: 'Subscribe',
    ctaUrl: '#',
  },
  allowsChildren: true,
};
