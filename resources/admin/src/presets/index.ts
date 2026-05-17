import type { BlockData } from '@/types/blocks';
import { heroPreset } from './hero';
import { ctaPreset } from './cta';
import { featuresPreset } from './features';
import { testimonialsPreset } from './testimonials';
import { pricingPreset } from './pricing';

export interface PresetDefinition {
  type: string;
  label: string;
  icon: string;
  description: string;
  build: () => BlockData;
}

export const presets: PresetDefinition[] = [
  heroPreset,
  ctaPreset,
  featuresPreset,
  testimonialsPreset,
  pricingPreset,
];

export function getPreset(type: string): PresetDefinition | undefined {
  return presets.find(p => p.type === type);
}
