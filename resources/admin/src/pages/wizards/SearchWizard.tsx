import { useMemo, useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Loader2, Check, ArrowLeft } from 'lucide-react';
import api, { collections as collectionsApi, type Collection } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

const SEARCHABLE_TYPES = ['text', 'sku', 'rich_text', 'email', 'url', 'phone'];
const FACETABLE_TYPES = ['select', 'multi_select', 'boolean', 'relation'];

/** S6 — Search Wizard: pick a collection, choose searchable/facet fields, build a search page. */
export default function SearchWizard() {
  const { siteId = '' } = useParams();
  const { toast } = useToast();

  const [collectionId, setCollectionId] = useState('');
  const [searchable, setSearchable] = useState<string[]>([]);
  const [facets, setFacets] = useState<string[]>([]);
  const [done, setDone] = useState<any>(null);

  const { data: allCollections = [] } = useQuery<Collection[]>({
    queryKey: ['collections', siteId],
    queryFn: () => collectionsApi.list(siteId).then((r) => r.data.data),
  });
  const collection = allCollections.find((c) => c.id === collectionId) ?? null;
  const fields = collection?.schema?.fields ?? [];

  const searchableFields = useMemo(() => fields.filter((f) => SEARCHABLE_TYPES.includes(f.type)), [fields]);
  const facetFields = useMemo(() => fields.filter((f) => FACETABLE_TYPES.includes(f.type)), [fields]);

  const pick = (list: string[], set: (v: string[]) => void, key: string) =>
    set(list.includes(key) ? list.filter((k) => k !== key) : [...list, key]);

  const submit = useMutation({
    mutationFn: () => api.post(`/sites/${siteId}/wizard/search`, {
      collection_id: collectionId,
      searchable,
      facets: facets.slice(0, 8),
      build_page: true,
    }),
    onSuccess: (res) => setDone(res.data.data),
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message ?? 'Could not build the search page.' }),
  });

  const selectCollection = (id: string) => {
    setCollectionId(id);
    const c = allCollections.find((x) => x.id === id);
    const f = c?.schema?.fields ?? [];
    setSearchable(f.filter((x) => x.searchable || SEARCHABLE_TYPES.includes(x.type)).slice(0, 5).map((x) => x.key));
    setFacets(f.filter((x) => x.facetable).map((x) => x.key));
  };

  if (done) {
    return (
      <div className="max-w-xl mx-auto text-center py-16">
        <div className="w-12 h-12 rounded-full bg-success/10 text-success flex items-center justify-center mx-auto mb-4"><Check /></div>
        <h1 className="text-lg font-semibold mb-2">Search page created</h1>
        <p className="text-[13px] text-base-content/60 mb-6">Search box, facets and a results grid are wired to “{collection?.name}”. Publish the page to make search live.</p>
        <div className="flex justify-center gap-3">
          {done.page && <Link to={`/sites/${siteId}/pages/${done.page.id}/edit`} className="btn btn-primary btn-sm text-[12px]">Open the page</Link>}
          <button onClick={() => { setDone(null); setCollectionId(''); }} className="btn btn-ghost btn-sm text-[12px]">Another</button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="flex items-center gap-2 mb-1">
        <Link to={`/sites/${siteId}/wizards`} className="btn btn-ghost btn-sm btn-square"><ArrowLeft size={16} /></Link>
        <h1 className="text-xl font-semibold">Search Wizard</h1>
      </div>
      <p className="text-[13px] text-base-content/50 mb-6 ml-10">Make a collection searchable and build a listing page — search box, facet filters, results grid.</p>

      <div className="border border-base-300/40 rounded-box bg-base-100 p-5 space-y-4">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Collection</label>
          <select value={collectionId} onChange={(e) => selectCollection(e.target.value)} className="select select-bordered select-sm w-full text-[13px]">
            <option value="">— pick a collection —</option>
            {allCollections.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>

        {collection && (
          <>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1.5 block">Searchable fields (full-text)</label>
              {searchableFields.length === 0 ? <p className="text-[12px] text-base-content/35">No text-like fields to search.</p> : (
                <div className="flex flex-wrap gap-1.5">
                  {searchableFields.map((f) => (
                    <button key={f.key} onClick={() => pick(searchable, setSearchable, f.key)}
                      className={`badge badge-sm cursor-pointer ${searchable.includes(f.key) ? 'badge-primary' : 'badge-ghost'}`}>{f.label || f.key}</button>
                  ))}
                </div>
              )}
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1.5 block">Facet filters (max 8)</label>
              {facetFields.length === 0 ? <p className="text-[12px] text-base-content/35">No select/boolean/relation fields to facet on.</p> : (
                <div className="flex flex-wrap gap-1.5">
                  {facetFields.map((f) => (
                    <button key={f.key} onClick={() => pick(facets, setFacets, f.key)}
                      className={`badge badge-sm cursor-pointer ${facets.includes(f.key) ? 'badge-primary' : 'badge-ghost'}`}>{f.label || f.key}</button>
                  ))}
                </div>
              )}
            </div>
            <div className="flex justify-end">
              <button onClick={() => submit.mutate()} disabled={submit.isPending} className="btn btn-primary btn-sm text-[12px]">
                {submit.isPending && <Loader2 size={13} className="animate-spin" />} Build search page
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
