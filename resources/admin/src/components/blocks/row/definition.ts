import type { BlockDefinition } from '@/types/blocks';

export type RowLayout =
  | '1'
  | '1/2+1/2'
  | '1/3+2/3'
  | '2/3+1/3'
  | '1/3+1/3+1/3'
  | '1/4+1/4+1/4+1/4'
  | '1/4+3/4'
  | '3/4+1/4';

export const LAYOUT_GRID: Record<string, string> = {
  '1': '1fr',
  '1/1': '1fr',
  '1/2+1/2': '1fr 1fr',
  '1/3+2/3': '1fr 2fr',
  '2/3+1/3': '2fr 1fr',
  '1/3+1/3+1/3': '1fr 1fr 1fr',
  '1/4+1/4+1/4+1/4': '1fr 1fr 1fr 1fr',
  '1/4+3/4': '1fr 3fr',
  '3/4+1/4': '3fr 1fr',
};

export const LAYOUT_LABELS: Record<RowLayout, string> = {
  '1': '1 Column',
  '1/2+1/2': '2 Col — 1/2 + 1/2',
  '1/3+2/3': '2 Col — 1/3 + 2/3',
  '2/3+1/3': '2 Col — 2/3 + 1/3',
  '1/3+1/3+1/3': '3 Col — Equal',
  '1/4+1/4+1/4+1/4': '4 Col — Equal',
  '1/4+3/4': '2 Col — 1/4 + 3/4',
  '3/4+1/4': '2 Col — 3/4 + 1/4',
};

export const LAYOUT_COLUMN_COUNT: Record<string, number> = {
  '1': 1,
  '1/1': 1,
  '1/2+1/2': 2,
  '1/3+2/3': 2,
  '2/3+1/3': 2,
  '1/3+1/3+1/3': 3,
  '1/4+1/4+1/4+1/4': 4,
  '1/4+3/4': 2,
  '3/4+1/4': 2,
};

export const rowDefinition: BlockDefinition = {
  type: 'row',
  category: 'layout',
  label: 'Row',
  icon: 'Rows3',
  level: 'row',
  defaultData: {
    layout: '1/2+1/2',
    gap: '16px',
    max_width: '',
    vertical_align: 'stretch',
  },
  allowsChildren: true,
  maxChildren: 6,
};
