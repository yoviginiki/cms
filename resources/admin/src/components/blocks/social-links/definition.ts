import type { BlockDefinition } from '@/types/blocks';

export const SOCIAL_NETWORKS: Record<string, string> = {
  facebook: 'Facebook', instagram: 'Instagram', x: 'X (Twitter)', linkedin: 'LinkedIn', youtube: 'YouTube',
  tiktok: 'TikTok', github: 'GitHub', whatsapp: 'WhatsApp', telegram: 'Telegram', contact: 'Contact', email: 'Email', phone: 'Phone', website: 'Website',
};

export const socialLinksDefinition: BlockDefinition = {
  type: 'social-links',
  category: 'navigation',
  label: 'Social Links',
  icon: 'Share2',
  defaultData: {
    links: [
      { network: 'facebook', url: '' },
      { network: 'instagram', url: '' },
    ],
    style: 'circle',
    size: 'md',
    color: '',
    showLabels: false,
    align: 'left',
  },
  allowsChildren: false,
};
