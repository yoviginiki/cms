import type { BlockDefinition } from '@/types/blocks';

export const audioDefinition: BlockDefinition = {
  type: 'audio',
  category: 'media',
  label: 'Audio',
  icon: 'Music',
  defaultData: {
    url: '',
    title: '',
    artist: '',
  },
  allowsChildren: false,
};
