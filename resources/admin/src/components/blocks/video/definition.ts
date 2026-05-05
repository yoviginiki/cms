import type { BlockDefinition } from '@/types/blocks';

export const videoDefinition: BlockDefinition = {
  type: 'video',
  category: 'media',
  label: 'Video',
  icon: '🎬',
  defaultData: {
    url: '',
    autoplay: false,
    muted: false,
    poster: '',
  },
  allowsChildren: false,
};
