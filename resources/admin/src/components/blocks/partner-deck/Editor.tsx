import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { TextArea } from '@/components/editor/fields/TextArea';

interface Card { title: string; body: string }

export const PartnerDeckEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { eyebrow?: string; buttonLabel?: string; cards?: Card[] };
  const cards: Card[] = Array.isArray(data.cards) ? data.cards : [];
  const set = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const setCard = (i: number, key: keyof Card, value: unknown) => set('cards', cards.map((c, idx) => (idx === i ? { ...c, [key]: value } : c)));
  const addCard = () => set('cards', [...cards, { title: 'New card', body: '' }]);
  const removeCard = (i: number) => set('cards', cards.filter((_, idx) => idx !== i));

  return (
    <div className="space-y-3">
      <TextField label="Eyebrow" value={data.eyebrow || ''} onChange={(v) => set('eyebrow', v)} />
      <TextField label="Button label" value={data.buttonLabel || ''} onChange={(v) => set('buttonLabel', v)} placeholder="Draw another" />
      <div className="pt-2 border-t border-gray-200">
        <div className="flex items-center justify-between mb-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">Cards ({cards.length})</span>
          <button type="button" onClick={addCard} className="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200">+ Add card</button>
        </div>
        <div className="space-y-3">
          {cards.map((c, i) => (
            <div key={i} className="p-2 rounded border border-gray-200 space-y-2">
              <div className="flex items-center gap-2">
                <div className="flex-1"><TextField label={`Card ${i + 1} title`} value={c.title || ''} onChange={(v) => setCard(i, 'title', v)} /></div>
                <button type="button" onClick={() => removeCard(i)} className="text-xs text-red-600 mt-5">Remove</button>
              </div>
              <TextArea label="Body" value={c.body || ''} onChange={(v) => setCard(i, 'body', v)} rows={3} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
