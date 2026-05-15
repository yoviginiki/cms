import { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField, ToggleField, SelectField, ColorField } from '@/components/editor/fields';

export const TextEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const content = (data.content as string) || '';
  const [showSource, setShowSource] = useState(false);
  // Send only changed field — store merges, preserving style/animation/advanced
  const update = (field: string, value: unknown) => onUpdate({ [field]: value });

  return (
    <div className="space-y-3">
      <p className="text-xs text-base-content/40">Edit directly in the block preview above.</p>
      <ToggleField label="Show HTML source" value={showSource} onChange={setShowSource} />
      {showSource && (
        <textarea value={content}
          onChange={e => update('content', e.target.value)}
          className="textarea textarea-bordered textarea-sm w-full text-[11px] font-mono h-40" />
      )}

      {/* ── Typography — Text Content ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Typography — Text Content</div>
      <SelectField
        label="Text Alignment"
        value={(data.textAlign as string) || ''}
        onChange={(v) => update('textAlign', v)}
        options={[
          { value: '', label: 'Default (inherit)' },
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
          { value: 'justify', label: 'Justify' },
        ]}
        helperText="Controls text alignment inside the block. Block position is set in the Layout panel."
      />
      <ColorField
        label="Text Color"
        value={(data.textColor as string) || ''}
        onChange={(v) => update('textColor', v)}
      />
      <TextField
        label="Font Size"
        value={(data.fontSize as string) || ''}
        onChange={(v) => update('fontSize', v)}
        placeholder="e.g. 1rem, 16px"
        helperText="Leave empty for default"
      />
      <SelectField
        label="Font Weight"
        value={(data.fontWeight as string) || ''}
        onChange={(v) => update('fontWeight', v)}
        options={[
          { value: '', label: 'Default (Normal)' },
          { value: '300', label: 'Light (300)' },
          { value: '400', label: 'Normal (400)' },
          { value: '500', label: 'Medium (500)' },
          { value: '600', label: 'Semibold (600)' },
          { value: '700', label: 'Bold (700)' },
        ]}
      />
      <ToggleField
        label="Italic"
        value={data.fontStyle === 'italic'}
        onChange={(v) => update('fontStyle', v ? 'italic' : '')}
      />
      <TextField
        label="Line Height"
        value={(data.lineHeight as string) || ''}
        onChange={(v) => update('lineHeight', v)}
        placeholder="e.g. 1.6, 24px"
        helperText="Leave empty for default"
      />
      <TextField
        label="Letter Spacing"
        value={(data.letterSpacing as string) || ''}
        onChange={(v) => update('letterSpacing', v)}
        placeholder="e.g. 0.02em, 1px"
        helperText="Leave empty for default"
      />
    </div>
  );
};
