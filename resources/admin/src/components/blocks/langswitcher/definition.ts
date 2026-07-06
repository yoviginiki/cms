import type { BlockDefinition } from '@/types/blocks';

export const langswitcherDefinition: BlockDefinition = {
  type: 'langswitcher',
  category: 'navigation',
  label: 'Language Switcher',
  icon: 'Languages',
  description: 'Lets visitors switch between the site languages. Links to the translated version of the current page automatically.',
  defaultData: {
    style: 'inline',        // inline | dropdown
    display: 'code',        // code | name | flag | flag-code | flag-name
    flagSize: 18,
    fontSize: 14,
    gap: 10,
    uppercase: true,
    separator: 'none',      // slash | pipe | dot | none
    alignment: 'left',
    textColor: '',
    activeColor: '',
  },
  allowsChildren: false,
};
