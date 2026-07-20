import type { BlockEditorProps } from '@/types/blocks';
import { TextField, ToggleField } from '@/components/editor/fields';
import { QuerySelect } from '../collections-shared';

export const QueryTableEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Renders a saved query's result rows as a plain HTML table at publish (records, or grouped aggregates).
      </div>
      <QuerySelect value={(data.queryId as string | null) || null} onChange={(v) => update('queryId', v)} />
      <TextField label="Max rows" value={String(data.maxRows ?? 20)} onChange={(v) => update('maxRows', Math.max(1, Math.min(100, Number(v) || 20)))} placeholder="20" />
      <ToggleField label="Header row" value={data.showHeader !== false} onChange={(v) => update('showHeader', v)} />
      <ToggleField label="Striped rows" value={data.striped !== false} onChange={(v) => update('striped', v)} />
    </div>
  );
};
