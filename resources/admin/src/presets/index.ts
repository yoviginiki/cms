import type { BlockData } from '@/types/blocks';
import { heroPreset } from './hero';
import { ctaPreset } from './cta';
import { featuresPreset } from './features';
import { testimonialsPreset } from './testimonials';
import { pricingPreset } from './pricing';
import { faqPreset } from './faq';
import { contactPreset } from './contact';
import { teamPreset } from './team';
import { statsPreset } from './stats';
import { portfolioPreset } from './portfolio';
import { blogGridPreset } from './bloggrid';

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
  faqPreset,
  contactPreset,
  teamPreset,
  statsPreset,
  portfolioPreset,
  blogGridPreset,
];

export function getPreset(type: string): PresetDefinition | undefined {
  return presets.find(p => p.type === type);
}
