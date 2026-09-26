import type { BlockDefinition } from '@/types/blocks';

export const siteIdentityDefinition: BlockDefinition = {
  type: 'site-identity',
  category: 'navigation',
  label: 'Site Logo / Name',
  icon: 'BadgeCheck',
  defaultData: { show: 'auto', showTagline: false, size: 'md', linkHome: true, align: 'left' },
  allowsChildren: false,
};
