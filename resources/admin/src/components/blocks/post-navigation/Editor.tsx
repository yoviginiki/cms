import type { BlockEditorProps } from '@/types/blocks';
import { ToggleField, SelectField } from '@/components/editor/fields';

export const PostNavigationEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Shows links to the previous and next posts. The links are resolved dynamically based on publish order.
      </div>
      <ToggleField label="Show Labels" value={data.showLabels !== false} onChange={v => update('showLabels', v)} />
      <SelectField label="Style" value={(data.style as string) || 'minimal'} onChange={v => update('style', v)}
        options={[
          { value: 'minimal', label: 'Minimal' },
          { value: 'buttons', label: 'Buttons' },
          { value: 'full', label: 'Full (with titles)' },
        ]} />
    </div>
  );
};
