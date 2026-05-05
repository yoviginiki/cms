import type { BlockDefinition } from '@/types/blocks';

export const chartDefinition: BlockDefinition = {
  type: 'chart',
  category: 'data',
  label: 'Chart',
  icon: 'BarChart3',
  defaultData: {
    chartType: 'bar',
    data: [
      { label: 'A', value: 30 },
      { label: 'B', value: 70 },
      { label: 'C', value: 50 },
    ],
    title: '',
    showLegend: true,
  },
  allowsChildren: false,
};
