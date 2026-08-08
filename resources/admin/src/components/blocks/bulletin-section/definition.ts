import type { BlockDefinition } from '@/types/blocks';

export const bulletinSectionDefinition: BlockDefinition = {
  type: 'bulletin-section',
  category: 'content',
  label: 'Bulletin section',
  icon: 'CalendarRange',
  description: 'A titled group of cultural events.',
  level: 'section',
  defaultData: { title: '' },
  allowsChildren: true,
  maxChildren: 100,
};
