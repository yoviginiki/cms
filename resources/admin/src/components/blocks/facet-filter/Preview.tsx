import type { BlockComponentProps } from '@/types/blocks';
import { useCollectionName } from '../collections-shared';

export const FacetFilterPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const fields = ((data.fields as string[]) || []).slice(0, 8);
  const style = (data.style as string) || 'checkbox';
  const collectionName = useCollectionName(data.collectionId as string | null);

  return (
    <div className="relative">
      <div className="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] px-1.5 py-0.5 rounded-bl font-medium z-10">Dynamic</div>
      {fields.length === 0 ? (
        <div className="border border-dashed border-gray-200 rounded p-4 text-center text-xs text-gray-400">
          Facet Filter — pick facet fields in the block settings
        </div>
      ) : (
        <div className="space-y-3">
          {fields.map(key => (
            <fieldset key={key} className="border border-gray-200 rounded p-2">
              <legend className="text-[11px] font-medium text-gray-500 px-1">{key}</legend>
              {style === 'dropdown' ? (
                <div className="h-7 bg-gray-100 rounded border border-gray-200" />
              ) : (
                <div className="space-y-1.5">
                  {[0, 1, 2].map(i => (
                    <div key={i} className="flex items-center gap-2">
                      <div className="w-3 h-3 border border-gray-300 rounded-sm shrink-0" />
                      <div className="h-2 bg-gray-100 rounded w-2/3" />
                    </div>
                  ))}
                </div>
              )}
            </fieldset>
          ))}
        </div>
      )}
      <p className="text-center text-xs text-indigo-400 mt-2 font-medium">
        Facet Filter — {collectionName || 'inherited collection'} · {style}
      </p>
    </div>
  );
};
