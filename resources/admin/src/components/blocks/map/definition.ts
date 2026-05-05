import type { BlockDefinition } from '@/types/blocks';

export const mapDefinition: BlockDefinition = {
  type: 'map',
  category: 'data',
  label: 'Map',
  icon: 'MapPin',
  defaultData: {
    lat: 42.6977,
    lng: 23.3219,
    zoom: 13,
    markerLabel: '',
    height: '400px',
  },
  allowsChildren: false,
};
