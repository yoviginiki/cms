import type { BlockEditorProps } from '@/types/blocks';
import { TextField, SelectField, ColorField } from '@/components/editor/fields';

export const HeadingEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      {/* ── Content ── */}
      <TextField
        label="Text"
        value={(data.text as string) || ''}
        onChange={(v) => update('text', v)}
        placeholder="Heading text"
      />
      <SelectField
        label="Heading Level"
        value={(data.level as string) || 'h2'}
        onChange={(v) => update('level', v)}
        options={[
          { value: 'h1', label: 'H1' },
          { value: 'h2', label: 'H2' },
          { value: 'h3', label: 'H3' },
          { value: 'h4', label: 'H4' },
          { value: 'h5', label: 'H5' },
          { value: 'h6', label: 'H6' },
        ]}
        helperText="Use only one H1 per page for SEO."
      />

      {/* ── Typography ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <ColorField
        label="Color"
        value={(data.color as string) || ''}
        onChange={(v) => update('color', v)}
      />
      <TextField
        label="Font Size"
        value={(data.fontSize as string) || ''}
        onChange={(v) => update('fontSize', v)}
        placeholder="Leave empty for level default"
        helperText="Override the default size for this heading level"
      />
      <SelectField
        label="Font Weight"
        value={(data.fontWeight as string) || ''}
        onChange={(v) => update('fontWeight', v)}
        options={[
          { value: '', label: 'Default (Bold)' },
          { value: '400', label: 'Normal (400)' },
          { value: '500', label: 'Medium (500)' },
          { value: '600', label: 'Semibold (600)' },
          { value: '700', label: 'Bold (700)' },
          { value: '800', label: 'Extra Bold (800)' },
          { value: '900', label: 'Black (900)' },
        ]}
      />
      <TextField
        label="Line Height"
        value={(data.lineHeight as string) || ''}
        onChange={(v) => update('lineHeight', v)}
        placeholder="e.g. 1.2, 1.5, 48px"
        helperText="Leave empty for default"
      />
      <TextField
        label="Letter Spacing"
        value={(data.letterSpacing as string) || ''}
        onChange={(v) => update('letterSpacing', v)}
        placeholder="e.g. 0.02em, -0.5px"
        helperText="Leave empty for default"
      />
      <SelectField
        label="Text Transform"
        value={(data.textTransform as string) || ''}
        onChange={(v) => update('textTransform', v)}
        options={[
          { value: '', label: 'None' },
          { value: 'uppercase', label: 'UPPERCASE' },
          { value: 'lowercase', label: 'lowercase' },
          { value: 'capitalize', label: 'Capitalize' },
        ]}
      />
      <SelectField
        label="Text Alignment"
        value={(data.textAlign as string) || ''}
        onChange={(v) => update('textAlign', v)}
        options={[
          { value: '', label: 'Default (inherit)' },
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]}
      />
    </div>
  );
};
