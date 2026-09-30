import type { BlockDefinition } from '@/types/blocks';

export const spotifyDefinition: BlockDefinition = {
  type: 'spotify',
  category: 'media',
  label: 'Spotify',
  icon: 'Music',
  defaultData: {
    url: '',
    theme: 'color',
    size: 'normal',
  },
  allowsChildren: false,
};
