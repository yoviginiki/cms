import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const textIntroPreset: PresetDefinition = {
  type: 'preset_text_intro',
  label: 'Text Intro',
  icon: 'AlignCenter',
  description: 'Centered intro with heading and paragraph',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: {
      padding_top: '80px',
      padding_bottom: '80px',
      max_width: '700px',
    },
    children: [{
      id: uid(),
      type: 'row',
      level: 'row',
      order: 0,
      data: { layout: '1', gap: '16px' },
      children: [{
        id: uid(),
        type: 'column',
        level: 'column',
        order: 0,
        data: {},
        children: [
          {
            id: uid(),
            type: 'heading',
            level: 'module',
            order: 0,
            data: { text: 'Welcome to Our Story', level: 'h2', fontSize: '2rem', textAlign: 'center' },
            children: [],
          },
          {
            id: uid(),
            type: 'paragraph',
            level: 'module',
            order: 1,
            data: { content: '<p style="text-align:center;color:var(--color-text-muted,#64748b);">We believe in creating meaningful experiences that connect people with ideas. Our mission is to make the web more beautiful, one site at a time.</p>' },
            children: [],
          },
        ],
      }],
    }],
  }),
};
