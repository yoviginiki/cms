import type { BlockDefinition } from '@/types/blocks';

export const buttonDefinition: BlockDefinition = {
  type: 'button',
  category: 'content',
  label: 'Button',
  icon: '🔘',
  defaultData: {
    text: 'Click Me',
    url: '#',
    style: 'primary',
    size: 'md',
    target: '_self',
    iconLeft: '',
    iconRight: '',
    fullWidth: false,
  },
  allowsChildren: false,
};
