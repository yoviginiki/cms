import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

function teamMember(name: string, role: string, order: number): BlockData {
  return {
    id: uid(), type: 'column', level: 'column', order, data: { vertical_align: 'center' },
    children: [
      { id: uid(), type: 'image', level: 'module', order: 0, data: { src: '', alt: name, fit: 'cover', borderRadius: '50%', width: '120px', height: '120px' }, children: [] },
      { id: uid(), type: 'heading', level: 'module', order: 1, data: { text: name, level: 'h3', fontSize: '1.25rem' }, children: [] },
      { id: uid(), type: 'paragraph', level: 'module', order: 2, data: { content: `<p style="color:#6b7280;text-align:center;">${role}</p>` }, children: [] },
    ],
  };
}

export const teamPreset: PresetDefinition = {
  type: 'preset_team',
  label: 'Team Section',
  icon: 'Users',
  description: 'Team members grid with photos and roles',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '1100px' },
    children: [
      {
        id: uid(), type: 'row', level: 'row', order: 0,
        data: { layout: '1', gap: '16px' },
        children: [{
          id: uid(), type: 'column', level: 'column', order: 0, data: {},
          children: [
            { id: uid(), type: 'heading', level: 'module', order: 0, data: { text: 'Meet Our Team', level: 'h2', fontSize: '2rem' }, children: [] },
            { id: uid(), type: 'paragraph', level: 'module', order: 1, data: { content: '<p style="color:#6b7280;text-align:center;">The people behind the product.</p>' }, children: [] },
          ],
        }],
      },
      {
        id: uid(), type: 'row', level: 'row', order: 1,
        data: { layout: '1/3+1/3+1/3', gap: '32px' },
        children: [
          teamMember('Alex Johnson', 'CEO & Founder', 0),
          teamMember('Sarah Chen', 'CTO', 1),
          teamMember('Mike Davis', 'Lead Designer', 2),
        ],
      },
    ],
  }),
};
