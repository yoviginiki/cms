import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields';
import { CollectionSelect } from '../collections-shared';

export const SearchBoxEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const collectionId = (data.collectionId as string | null) || null;

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Search input for collection records — pair with Facet Filter and Results Grid on the same page
      </div>
      <CollectionSelect value={collectionId} onChange={v => update('collectionId', v)}
        unsetLabel="— inherited from archive —"
        helperText={!collectionId ? 'Inside a record template the collection is inherited from the template' : undefined} />
      <TextField label="Placeholder" value={(data.placeholder as string) || ''} onChange={v => update('placeholder', v)}
        placeholder="Defaults to “Search {collection}…”" />
    </div>
  );
};
