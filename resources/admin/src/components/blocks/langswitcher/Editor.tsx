import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';
import { NumberField } from '@/components/editor/fields/NumberField';
import { ToggleField } from '@/components/editor/fields/ToggleField';
import { ColorField } from '@/components/editor/fields/ColorField';

export const LangSwitcherEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    style?: string;
    display?: string;
    flagSize?: number;
    fontSize?: number;
    gap?: number;
    uppercase?: boolean;
    separator?: string;
    alignment?: string;
    textColor?: string;
    activeColor?: string;
  };

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const display = data.display || 'code';
  const showFlagSize = display.startsWith('flag');
  const showText = display !== 'flag';

  return (
    <div className="space-y-3">
      <SelectField
        label="Style"
        value={data.style || 'inline'}
        onChange={(v) => update('style', v)}
        options={[
          { value: 'inline', label: 'Inline (EN / BG)' },
          { value: 'dropdown', label: 'Dropdown' },
        ]}
      />
      <SelectField
        label="Show as"
        value={display}
        onChange={(v) => update('display', v)}
        options={[
          { value: 'code', label: 'Code (EN, BG)' },
          { value: 'name', label: 'Full name (English, Български)' },
          { value: 'flag', label: 'Flag only' },
          { value: 'flag-code', label: 'Flag + code' },
          { value: 'flag-name', label: 'Flag + name' },
        ]}
      />
      {showFlagSize && (
        <NumberField label="Flag size (px)" value={data.flagSize || 18}
          onChange={(v) => update('flagSize', v)} min={10} max={64} />
      )}
      {showText && (
        <>
          <NumberField label="Text size (px)" value={data.fontSize || 14}
            onChange={(v) => update('fontSize', v)} min={9} max={48} />
          <ToggleField label="Uppercase codes" value={data.uppercase ?? true}
            onChange={(v) => update('uppercase', v)} />
        </>
      )}
      {(data.style || 'inline') === 'inline' && (
        <>
          <SelectField
            label="Separator"
            value={data.separator || 'none'}
            onChange={(v) => update('separator', v)}
            options={[
              { value: 'none', label: 'None' },
              { value: 'slash', label: 'Slash ( / )' },
              { value: 'pipe', label: 'Pipe ( | )' },
              { value: 'dot', label: 'Dot ( · )' },
            ]}
          />
          <NumberField label="Spacing (px)" value={data.gap ?? 10}
            onChange={(v) => update('gap', v)} min={2} max={48} />
        </>
      )}
      <SelectField
        label="Alignment"
        value={data.alignment || 'left'}
        onChange={(v) => update('alignment', v)}
        options={[
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]}
      />
      {showText && (
        <>
          <ColorField label="Text color" value={data.textColor || ''}
            onChange={(v) => update('textColor', v)} />
          <ColorField label="Active language color" value={data.activeColor || ''}
            onChange={(v) => update('activeColor', v)} />
        </>
      )}
      <p className="text-[10px] text-gray-400 leading-snug">
        Shows all languages enabled in Site Settings → Languages. On the published site each
        item links to the translated version of the current page (or the language home page
        when no translation exists), and the active language is highlighted.
      </p>
    </div>
  );
};
