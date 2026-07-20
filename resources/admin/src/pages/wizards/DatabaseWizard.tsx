import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Plus, Trash2, Loader2, Check, ArrowLeft } from 'lucide-react';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

interface WizField {
  label: string;
  type: string;
  required?: boolean;
  target?: string;   // relation: collection name
  mode?: 'one' | 'many';
  options?: string;  // select: comma-separated (UI only)
}
interface WizCollection {
  name: string;
  hierarchical?: boolean;
  fields: WizField[];
}

const SCALAR_TYPES = [
  ['text', 'Text'], ['rich_text', 'Rich text'], ['number', 'Number'], ['price', 'Price'],
  ['boolean', 'Yes/No'], ['date', 'Date'], ['select', 'Dropdown'], ['multi_select', 'Multi-select'],
  ['image', 'Image'], ['gallery', 'Gallery'], ['email', 'Email'], ['url', 'URL'], ['phone', 'Phone'], ['sku', 'SKU'],
  ['relation', 'Relation (link to another collection)'],
];

/**
 * S6 — Database Wizard (and, with `mode="app"`, the App Wizard): build a set
 * of collections with fields/relations/hierarchy. App mode also scaffolds a
 * detail template + index page per collection and a search page.
 */
export default function DatabaseWizard({ mode = 'database' }: { mode?: 'database' | 'app' }) {
  const { siteId = '' } = useParams();
  const { toast } = useToast();
  const isApp = mode === 'app';

  const [collections, setCollections] = useState<WizCollection[]>([
    { name: '', fields: [{ label: 'Name', type: 'text', required: true }] },
  ]);
  const [searchFor, setSearchFor] = useState('');
  const [done, setDone] = useState<any>(null);

  const names = collections.map((c) => c.name.trim()).filter(Boolean);

  const setCollection = (i: number, patch: Partial<WizCollection>) =>
    setCollections(collections.map((c, j) => (j === i ? { ...c, ...patch } : c)));
  const setField = (ci: number, fi: number, patch: Partial<WizField>) =>
    setCollection(ci, { fields: collections[ci].fields.map((f, j) => (j === fi ? { ...f, ...patch } : f)) });

  const payloadCollections = () => collections
    .filter((c) => c.name.trim())
    .map((c) => ({
      name: c.name.trim(),
      hierarchical: !!c.hierarchical,
      fields: c.fields.filter((f) => f.label.trim()).map((f) => ({
        label: f.label.trim(),
        type: f.type,
        required: !!f.required,
        ...(f.type === 'relation' ? { target: f.target, mode: f.mode ?? 'one' } : {}),
        ...(['select', 'multi_select'].includes(f.type)
          ? { options: (f.options ?? '').split(',').map((s) => s.trim()).filter(Boolean) } : {}),
      })),
    }));

  const submit = useMutation({
    mutationFn: () => api.post(`/sites/${siteId}/wizard/${isApp ? 'app' : 'database'}`, {
      collections: payloadCollections(),
      ...(isApp ? { pages_for: names.map((n) => n.toLowerCase()), search_for: searchFor.toLowerCase() || null } : {}),
    }),
    onSuccess: (res) => setDone(res.data.data),
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message ?? 'Scaffolding failed — check relation targets.' }),
  });

  const canSubmit = names.length > 0 && collections.every((c) => !c.name.trim() || c.fields.some((f) => f.label.trim()));

  if (done) {
    return (
      <div className="max-w-xl mx-auto text-center py-16">
        <div className="w-12 h-12 rounded-full bg-success/10 text-success flex items-center justify-center mx-auto mb-4"><Check /></div>
        <h1 className="text-lg font-semibold mb-2">{isApp ? 'App scaffolded' : 'Collections created'}</h1>
        <p className="text-[13px] text-base-content/60 mb-6">
          Created {done.collections?.length} collection(s){isApp && done.index_pages ? `, ${done.index_pages.length} index page(s), and a search page` : ''}.
          Add records next, then publish.
        </p>
        <div className="flex justify-center gap-3">
          <Link to={`/sites/${siteId}/collections`} className="btn btn-primary btn-sm text-[12px]">Open Collections</Link>
          {isApp && <Link to={`/sites/${siteId}/pages`} className="btn btn-ghost btn-sm text-[12px]">View pages</Link>}
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto">
      <div className="flex items-center gap-2 mb-1">
        <Link to={`/sites/${siteId}/wizards`} className="btn btn-ghost btn-sm btn-square"><ArrowLeft size={16} /></Link>
        <h1 className="text-xl font-semibold">{isApp ? 'App Wizard' : 'Database Wizard'}</h1>
      </div>
      <p className="text-[13px] text-base-content/50 mb-6 ml-10">
        {isApp
          ? 'Design your collections — the wizard also builds a detail template, an index page for each, and a search page.'
          : 'Design one or more collections. Relation fields can point at any collection you define here (by name).'}
      </p>

      <div className="space-y-4">
        {collections.map((c, ci) => (
          <div key={ci} className="border border-base-300/40 rounded-box bg-base-100 p-4">
            <div className="flex items-center gap-2 mb-3">
              <input value={c.name} onChange={(e) => setCollection(ci, { name: e.target.value })}
                placeholder="Collection name (e.g. Products)" className="input input-bordered input-sm flex-1 text-[13px] font-medium" />
              <label className="flex items-center gap-1.5 text-[11px] text-base-content/60" title="Records nest under a parent (category tree)">
                <input type="checkbox" className="checkbox checkbox-xs" checked={!!c.hierarchical}
                  onChange={(e) => setCollection(ci, { hierarchical: e.target.checked })} /> hierarchical
              </label>
              {collections.length > 1 && (
                <button onClick={() => setCollections(collections.filter((_, j) => j !== ci))}
                  className="btn btn-ghost btn-xs btn-square text-base-content/40"><Trash2 size={13} /></button>
              )}
            </div>

            <div className="space-y-2">
              {c.fields.map((f, fi) => (
                <div key={fi} className="flex items-start gap-2 flex-wrap">
                  <input value={f.label} onChange={(e) => setField(ci, fi, { label: e.target.value })}
                    placeholder="Field label" className="input input-bordered input-sm text-[12px] w-40" />
                  <select value={f.type} onChange={(e) => setField(ci, fi, { type: e.target.value })}
                    className="select select-bordered select-sm text-[12px]">
                    {SCALAR_TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select>
                  {f.type === 'relation' && (
                    <>
                      <select value={f.target ?? ''} onChange={(e) => setField(ci, fi, { target: e.target.value })}
                        className="select select-bordered select-sm text-[12px]">
                        <option value="">— target —</option>
                        {names.map((n) => <option key={n} value={n}>{n}</option>)}
                      </select>
                      <select value={f.mode ?? 'one'} onChange={(e) => setField(ci, fi, { mode: e.target.value as 'one' | 'many' })}
                        className="select select-bordered select-sm text-[12px]">
                        <option value="one">one</option><option value="many">many</option>
                      </select>
                    </>
                  )}
                  {['select', 'multi_select'].includes(f.type) && (
                    <input value={f.options ?? ''} onChange={(e) => setField(ci, fi, { options: e.target.value })}
                      placeholder="options, comma, separated" className="input input-bordered input-sm text-[12px] w-48" />
                  )}
                  <label className="flex items-center gap-1 text-[11px] text-base-content/50 pt-1.5">
                    <input type="checkbox" className="checkbox checkbox-xs" checked={!!f.required}
                      onChange={(e) => setField(ci, fi, { required: e.target.checked })} /> req.
                  </label>
                  <button onClick={() => setCollection(ci, { fields: c.fields.filter((_, j) => j !== fi) })}
                    disabled={c.fields.length === 1} className="btn btn-ghost btn-xs btn-square text-base-content/40 mt-1"><Trash2 size={12} /></button>
                </div>
              ))}
              <button onClick={() => setCollection(ci, { fields: [...c.fields, { label: '', type: 'text' }] })}
                className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary"><Plus size={12} /> Add field</button>
            </div>
          </div>
        ))}

        <button onClick={() => setCollections([...collections, { name: '', fields: [{ label: 'Name', type: 'text', required: true }] }])}
          className="btn btn-ghost btn-sm gap-1 text-[12px] text-primary"><Plus size={14} /> Add collection</button>

        {isApp && names.length > 0 && (
          <div className="border border-base-300/40 rounded-box bg-base-100 p-4">
            <label className="text-[11px] text-base-content/50 mb-1 block">Build a search page for</label>
            <select value={searchFor} onChange={(e) => setSearchFor(e.target.value)} className="select select-bordered select-sm text-[13px] w-full max-w-xs">
              <option value="">— none —</option>
              {names.map((n) => <option key={n} value={n}>{n}</option>)}
            </select>
          </div>
        )}

        <div className="flex justify-end pt-2">
          <button onClick={() => submit.mutate()} disabled={!canSubmit || submit.isPending} className="btn btn-primary btn-sm text-[12px]">
            {submit.isPending && <Loader2 size={13} className="animate-spin" />} {isApp ? 'Scaffold app' : 'Create collections'}
          </button>
        </div>
      </div>
    </div>
  );
}
