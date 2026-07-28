import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField, ToggleField } from '@/components/editor/fields';
import { CategoryNodeSelect, CollectionSelect, useCategoryTree } from '../collections-shared';

export const CollectionCategoriesEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const collectionId = (data.collectionId as string | null) || null;
  const { data: tree, isLoading: treeLoading } = useCategoryTree(collectionId);

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Lists a collection's categories as linked cards — each opens the category's listing page
      </div>
      <CollectionSelect value={collectionId} onChange={v => update('collectionId', v)}
        unsetLabel="— pick a collection —" />
      {collectionId && !treeLoading && (tree ?? []).length === 0 && (
        <div className="text-xs text-warning p-2 border border-warning/40 rounded">
          This collection has no category tree yet — add categories in the collection's Categories tab.
        </div>
      )}
      <CategoryNodeSelect collectionId={collectionId}
        value={(data.parentNodeId as string | null) || null}
        onChange={v => update('parentNodeId', v)}
        label="Parent category"
        unsetLabel="— root level —"
        helperText="Shows the children of this category (root level when unset)" />
      <SelectField label="Layout" value={(data.layout as string) || 'cards'} onChange={v => update('layout', v)}
        options={[
          { value: 'cards', label: 'Cards (grid)' },
          { value: 'pills', label: 'Pills (inline tags)' },
          { value: 'list', label: 'List (rows)' },
        ]} />
      {((data.layout as string) || 'cards') === 'cards' && (
        <SelectField label="Columns" value={String(data.columns ?? 4)} onChange={v => update('columns', Number(v))}
          options={[
            { value: '2', label: '2' }, { value: '3', label: '3' },
            { value: '4', label: '4' }, { value: '5', label: '5' }, { value: '6', label: '6' },
          ]} />
      )}
      <ToggleField label="Show record count" value={(data.showCount as boolean) ?? true}
        onChange={v => update('showCount', v)} />
      <ToggleField label="Hide empty categories" value={(data.hideEmpty as boolean) ?? false}
        onChange={v => update('hideEmpty', v)}
        helperText="Skip categories with no published records (counting subcategories)" />
      <TextField label="Gap" value={(data.gap as string) || '1rem'} onChange={v => update('gap', v)}
        placeholder="1rem" />
    </div>
  );
};
