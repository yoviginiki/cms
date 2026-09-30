import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField, SelectField, ColorField, ToggleField } from '@/components/editor/fields';
import { LinkTargetPicker } from '@/components/editor/fields/LinkTargetPicker';
import { LINK_ICONS, LinkIcon, resolveLinkIcon } from './icons';

const ICON_LABELS: Record<string, string> = {
  link: 'Link', external: 'External', arrow: 'Arrow', music: 'Music', play: 'Play', spotify: 'Spotify',
  youtube: 'YouTube', instagram: 'Instagram', facebook: 'Facebook', mail: 'E-mail', phone: 'Phone',
  download: 'Download', file: 'File',
};

export const LinkEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const icon = (data.icon as string) || 'auto';
  const shown = resolveLinkIcon(icon, (data.linkUrl as string) || '');

  return (
    <div className="space-y-3">
      <TextField label="Text" value={(data.text as string) || ''} onChange={v => update('text', v)} placeholder="Listen on Spotify" />
      <LinkTargetPicker data={data} update={update} allowNone={false} />

      <div className="divider text-[10px] text-base-content/40 my-1">Icon</div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Icon</label>
        <div className="grid grid-cols-5 gap-1">
          {['auto', 'none', ...Object.keys(LINK_ICONS)].map(k => (
            <button key={k} type="button" title={k === 'auto' ? 'Auto (from the link)' : k === 'none' ? 'No icon' : ICON_LABELS[k]}
              onClick={() => update('icon', k)}
              className={`h-8 rounded border text-[10px] flex items-center justify-center ${icon === k ? 'border-primary bg-primary/10 text-primary' : 'border-base-300 text-base-content/60 hover:border-base-content/40'}`}>
              {k === 'auto' ? 'Auto' : k === 'none' ? '—' : <span className="text-[16px] flex"><LinkIcon name={k} /></span>}
            </button>
          ))}
        </div>
        {icon === 'auto' && <p className="text-[10px] text-base-content/40 mt-1">Auto: {shown ? ICON_LABELS[shown] : 'none'}</p>}
      </div>
      <div className="grid grid-cols-2 gap-2">
        <SelectField label="Icon side" value={(data.iconPosition as string) || 'left'} onChange={v => update('iconPosition', v)}
          options={[{ value: 'left', label: 'Before text' }, { value: 'right', label: 'After text' }]} />
        <ToggleField label="Brand color" value={!!data.brandColor} onChange={v => update('brandColor', v)} />
      </div>

      <div className="divider text-[10px] text-base-content/40 my-1">Style</div>
      <ColorField label="Color" value={(data.color as string) || ''} onChange={v => update('color', v)} />
      <div className="grid grid-cols-2 gap-2">
        <TextField label="Size" value={(data.fontSize as string) || ''} onChange={v => update('fontSize', v)} placeholder="e.g. 18px" />
        <SelectField label="Weight" value={(data.fontWeight as string) || ''} onChange={v => update('fontWeight', v)}
          options={[{ value: '', label: 'Default' }, { value: '400', label: 'Normal' }, { value: '500', label: 'Medium' }, { value: '600', label: 'Semibold' }, { value: '700', label: 'Bold' }]} />
      </div>
      <div className="grid grid-cols-2 gap-2">
        <SelectField label="Underline" value={(data.underline as string) || 'hover'} onChange={v => update('underline', v)}
          options={[{ value: 'hover', label: 'On hover' }, { value: 'always', label: 'Always' }, { value: 'none', label: 'Never' }]} />
        <SelectField label="Align" value={(data.align as string) || 'left'} onChange={v => update('align', v)}
          options={[{ value: 'left', label: 'Left' }, { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' }]} />
      </div>
    </div>
  );
};
