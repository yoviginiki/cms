import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields';

export const ArchivePaginationEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Page navigation for post archive listings
      </div>
      <SelectField label="Style" value={(data.style as string) || 'numbered'} onChange={v => update('style', v)}
        options={[
          { value: 'numbered', label: 'Numbered Pages' },
          { value: 'simple', label: 'Previous / Next' },
          { value: 'load-more', label: 'Load More Button' },
        ]} />
      <SelectField label="Alignment" value={(data.align as string) || 'center'} onChange={v => update('align', v)}
        options={[{ value: 'left', label: 'Left' }, { value: 'center', label: 'Center' }, { value: 'right', label: 'Right' }]} />
    </div>
  );
};
