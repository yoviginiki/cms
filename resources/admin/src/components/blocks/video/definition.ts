import type { BlockDefinition } from '@/types/blocks';

export const videoDefinition: BlockDefinition = {
  type: 'video',
  category: 'media',
  label: 'Video',
  icon: 'Video',
  defaultData: {
    url: '',
    autoplay: false,
    muted: false,
    loop: false,
    poster: '',
    heroMode: false,
    shape: 'none',
    shapeRadius: '',
    minHeight: '80vh',
    overlay: false,
    overlayColor: 'rgba(0,0,0,0.4)',
    overlayOpacity: 0.4,
    title: '',
    subtitle: '',
    textColor: '#ffffff',
  },
  allowsChildren: false,
};
