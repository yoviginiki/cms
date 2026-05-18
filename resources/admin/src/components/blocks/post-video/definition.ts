import type { BlockDefinition } from '@/types/blocks';

export const postVideoDefinition: BlockDefinition = {
  type: 'post-video',
  category: 'dynamic',
  label: 'Post Video',
  icon: 'Play',
  description: 'Shows video embed from post video URL',
  level: 'module',
  defaultData: {
    aspectRatio: '16:9',
    autoplay: false,
    controls: true,
  },
  allowsChildren: false,
};
