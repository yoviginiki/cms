import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

type Field = { key: string; label: string; placeholder?: string; type?: string };

const FIELDS: Field[] = [
  { key: 'title', label: 'Title', placeholder: 'Event name' },
  { key: 'start_at', label: 'Starts', type: 'datetime-local' },
  { key: 'end_at', label: 'Ends (optional)', type: 'datetime-local' },
  { key: 'city', label: 'City', placeholder: 'Sofia' },
  { key: 'venue', label: 'Venue', placeholder: 'National Palace of Culture' },
  { key: 'ticket_url', label: 'Ticket URL (optional)', placeholder: 'https://…' },
  { key: 'official_url', label: 'Official URL (optional)', placeholder: 'https://…' },
];

export const EventCardEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const set = (key: string, value: unknown) => onUpdate({ ...data, [key]: value });

  return (
    <div className="space-y-3">
      {FIELDS.map((f) => (
        <div key={f.key}>
          <label className="text-[11px] text-base-content/50 mb-1 block">{f.label}</label>
          <input
            type={f.type ?? 'text'}
            value={(data[f.key] as string) || ''}
            onChange={(e) => set(f.key, e.target.value)}
            className="input input-bordered input-sm w-full text-[12px]"
            placeholder={f.placeholder}
          />
        </div>
      ))}
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Short description</label>
        <textarea
          value={(data.short_description as string) || ''}
          onChange={(e) => set('short_description', e.target.value)}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]"
          rows={3}
          placeholder="One or two sentences about the event."
        />
      </div>
      <label className="flex items-center gap-2.5 cursor-pointer w-fit">
        <input
          type="checkbox"
          className="checkbox checkbox-xs"
          checked={!!data.is_free}
          onChange={(e) => set('is_free', e.target.checked)}
        />
        <span className="text-[12px] text-base-content/70">Free entry</span>
      </label>
    </div>
  );
};
