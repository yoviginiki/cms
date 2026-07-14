import { Link, useParams } from 'react-router-dom';
import { ExternalLink } from 'lucide-react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields';
import { CollectionSelect, FieldMultiPick, SchemaLoadingHint, isFacetField, useCollection } from '../collections-shared';

export const FacetFilterEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { siteId = '' } = useParams();
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const collectionId = (data.collectionId as string | null) || null;
  const { data: collection, isLoading: schemaLoading } = useCollection(collectionId);
  const facetFields = collection?.schema.fields.filter(isFacetField) ?? [];

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Facet filters for collection records — pair with Search Box and Results Grid on the same page
      </div>
      <CollectionSelect value={collectionId} onChange={v => update('collectionId', v)}
        unsetLabel="— inherited from archive —"
        helperText={!collectionId ? 'Inside a record template the collection is inherited from the template' : undefined} />
      {collectionId && schemaLoading && <SchemaLoadingHint />}
      {collection && facetFields.length === 0 && (
        <div className="text-[11px] text-base-content/50 bg-base-200/60 p-2 rounded">
          No facetable fields in this collection.{' '}
          <Link to={`/sites/${siteId}/collections/${collectionId}/schema`}
            className="link link-primary inline-flex items-center gap-0.5">
            Mark fields as facetable in the schema editor <ExternalLink size={10} />
          </Link>
        </div>
      )}
      {facetFields.length > 0 && (
        <FieldMultiPick label="Facet Fields" fields={facetFields}
          value={(data.fields as string[]) || []} onChange={v => update('fields', v)} max={8} />
      )}
      <SelectField label="Style" value={(data.style as string) || 'checkbox'} onChange={v => update('style', v)}
        options={[
          { value: 'checkbox', label: 'Checkboxes' },
          { value: 'dropdown', label: 'Dropdowns' },
        ]} />
    </div>
  );
};
