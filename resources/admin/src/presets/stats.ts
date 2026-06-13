import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

function statCol(number: string, label: string, order: number): BlockData {
  return {
    id: uid(), type: 'column', level: 'column', order, data: { vertical_align: 'center' },
    children: [
      { id: uid(), type: 'heading', level: 'module', order: 0, data: { text: number, level: 'h2', fontSize: '3rem', color: '#3b82f6' }, children: [] },
      { id: uid(), type: 'paragraph', level: 'module', order: 1, data: { content: `<p style="text-align:center;color:#6b7280;font-size:0.875rem;">${label}</p>` }, children: [] },
    ],
  };
}

export const statsPreset: PresetDefinition = {
  type: 'preset_stats',
  label: 'Stats Section',
  icon: 'BarChart3',
  description: 'Key metrics with large numbers',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '1100px', bg_type: 'color', bg_color: '#f8fafc' },
    children: [{
      id: uid(), type: 'row', level: 'row', order: 0,
      data: { layout: '1/4+1/4+1/4+1/4', gap: '24px' },
      children: [
        statCol('10K+', 'Active Users', 0),
        statCol('99.9%', 'Uptime', 1),
        statCol('50+', 'Countries', 2),
        statCol('24/7', 'Support', 3),
      ],
    }],
  }),
};
