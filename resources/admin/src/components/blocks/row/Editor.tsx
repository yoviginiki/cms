import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';
import { RowLayoutPicker } from './RowLayoutPicker';
import { ColumnWidthBar, StackOrderControl } from './ColumnControls';
import { LAYOUT_COLUMN_COUNT } from './definition';
import { presetToSpans, normalizeSpans } from '@/lib/columnLayout';

export const RowEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const layout = (data.layout as string) || '1/2+1/2';
  const hasSpans = Array.isArray(data.col_spans) && data.col_spans.length >= 1;
  const count = hasSpans ? (data.col_spans as number[]).length : (LAYOUT_COLUMN_COUNT[layout] || 2);
  const spans = hasSpans ? normalizeSpans(data.col_spans, count) : presetToSpans(layout, count);

  // Choosing a preset resets fine-grained widths + mobile order back to it.
  const onLayoutChange = (v: string) => {
    onUpdate({ ...block.data, layout: v, col_spans: undefined, stack_order: undefined });
  };

  return (
    <div className="space-y-3">
      <RowLayoutPicker value={layout} onChange={onLayoutChange} />

      {count > 1 && (
        <ColumnWidthBar spans={spans} onChange={(s) => update('col_spans', s)} />
      )}

      <TextField
        label="Gap"
        value={(data.gap as string) || '16px'}
        onChange={(v) => update('gap', v)}
        placeholder="16px"
      />
      <TextField
        label="Max Width"
        value={(data.max_width as string) || ''}
        onChange={(v) => update('max_width', v)}
        placeholder="e.g. 1000px"
      />
      <SelectField
        label="Vertical Align"
        value={(data.vertical_align as string) || 'stretch'}
        onChange={(v) => update('vertical_align', v)}
        options={[
          { value: 'start', label: 'Top' },
          { value: 'center', label: 'Center' },
          { value: 'end', label: 'Bottom' },
          { value: 'stretch', label: 'Stretch' },
        ]}
      />

      <StackOrderControl
        count={count}
        stackOrder={data.stack_order as number[] | undefined}
        onChange={(o) => update('stack_order', o)}
      />
    </div>
  );
};
