import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Loader2, Save, Rocket } from 'lucide-react';
import {
  collections, collectionRecords,
  type Collection, type CollectionRecord, type CollectionRecordPayload,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { slugify } from '@/lib/slugify';
import { apiErr, validationErrors } from './shared';
import { FieldInput, RelationInput, type SelectedRelation } from './RecordFields';

// ─────────────────────────────────────────────────────────────────────────────
// Auto-generated entry form: one typed input per schema field. Validation
// errors come back keyed data.{key} / relations.{key} and map inline.
// ─────────────────────────────────────────────────────────────────────────────

export default function CollectionRecordEditor() {
  const { siteId = '', collectionId = '', recordId } = useParams();
  const isNew = !recordId;
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: collection, isLoading: schemaLoading, error: schemaError } = useQuery<Collection>({
    queryKey: ['collection', siteId, collectionId],
    queryFn: () => collections.get(siteId, collectionId).then((r) => r.data.data),
  });

  const { data: record, isLoading: recordLoading, error: recordError } = useQuery<CollectionRecord>({
    queryKey: ['collection-record', siteId, collectionId, recordId],
    queryFn: () => collectionRecords.get(siteId, collectionId, recordId!).then((r) => r.data.data),
    enabled: !isNew,
  });

  const [data, setData] = useState<Record<string, unknown>>({});
  const [relations, setRelations] = useState<Record<string, SelectedRelation[]>>({});
  const [slug, setSlug] = useState('');
  const [slugTouched, setSlugTouched] = useState(false);
  const [status, setStatus] = useState<'draft' | 'published'>('draft');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loaded, setLoaded] = useState(false);

  // Hydrate form from the loaded record (edit) or defaults (new)
  useEffect(() => {
    if (isNew) { setLoaded(true); return; }
    if (!record) return;
    setData(record.data ?? {});
    setSlug(record.slug ?? '');
    setSlugTouched(true); // existing slugs shouldn't silently change with the title
    setStatus(record.status);
    const rels: Record<string, SelectedRelation[]> = {};
    for (const [key, items] of Object.entries(record.relations ?? {})) {
      rels[key] = [...items]
        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
        .map((it) => ({ id: it.id, title: it.title, pivot: (it.pivot as Record<string, unknown>) ?? {} }));
    }
    setRelations(rels);
    setLoaded(true);
  }, [record, isNew]);

  const fields = collection?.schema?.fields ?? [];
  const titleKey = collection?.schema?.title_field ?? '';
  const slugSourceKey = collection?.schema?.slug_source || titleKey;

  const setFieldValue = (key: string, v: unknown) => {
    setData((prev) => ({ ...prev, [key]: v }));
    setErrors((prev) => { const { [`data.${key}`]: _drop, ...rest } = prev; return rest; });
    // Auto-slug from the slug-source field until the user edits the slug
    if (isNew && !slugTouched && key === slugSourceKey && typeof v === 'string') {
      setSlug(slugify(v));
    }
  };

  const setRelationValue = (key: string, v: SelectedRelation[]) => {
    setRelations((prev) => ({ ...prev, [key]: v }));
    setErrors((prev) => { const { [`relations.${key}`]: _drop, ...rest } = prev; return rest; });
  };

  const buildPayload = (saveStatus: 'draft' | 'published'): CollectionRecordPayload => ({
    data: Object.fromEntries(Object.entries(data).filter(([, v]) => v !== undefined)),
    relations: Object.fromEntries(
      fields
        .filter((f) => f.type === 'relation')
        .map((f) => [
          f.key,
          (relations[f.key] ?? []).map((r) => ({
            id: r.id,
            ...(r.pivot && Object.keys(r.pivot).length > 0 ? { pivot: r.pivot } : {}),
          })),
        ]),
    ),
    status: saveStatus,
    ...(slug.trim() ? { slug: slug.trim() } : {}),
  });

  const saveMutation = useMutation({
    mutationFn: (saveStatus: 'draft' | 'published') => {
      const payload = buildPayload(saveStatus);
      return isNew
        ? collectionRecords.create(siteId, collectionId, payload)
        : collectionRecords.update(siteId, collectionId, recordId!, payload);
    },
    onSuccess: (_res, saveStatus) => {
      queryClient.invalidateQueries({ queryKey: ['collection-records', siteId, collectionId] });
      queryClient.invalidateQueries({ queryKey: ['collection-record', siteId, collectionId, recordId] });
      queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
      toast({ type: 'success', message: saveStatus === 'published' ? 'Saved & published.' : 'Saved.' });
      navigate(`/sites/${siteId}/collections/${collectionId}/records`);
    },
    onError: (e: any) => {
      const errs = validationErrors(e);
      if (Object.keys(errs).length > 0) {
        setErrors(errs);
        toast({ type: 'error', message: 'Some fields need attention.' });
      } else {
        toast({ type: 'error', message: apiErr(e) });
      }
    },
  });

  // Client-side required check keeps the round-trip short; server re-validates.
  const requiredMissing = (): string | null => {
    for (const f of fields) {
      if (!f.required) continue;
      if (f.type === 'relation') {
        if ((relations[f.key] ?? []).length === 0) return f.label;
      } else {
        const v = data[f.key];
        const empty = v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);
        if (empty) return f.label;
      }
    }
    return null;
  };

  const save = (saveStatus: 'draft' | 'published') => {
    const missing = requiredMissing();
    if (missing) {
      const errs: Record<string, string> = {};
      for (const f of fields) {
        if (!f.required) continue;
        const v = f.type === 'relation' ? relations[f.key] : data[f.key];
        const empty = v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);
        if (empty) errs[`${f.type === 'relation' ? 'relations' : 'data'}.${f.key}`] = 'This field is required';
      }
      setErrors(errs);
      toast({ type: 'error', message: `"${missing}" is required.` });
      return;
    }
    saveMutation.mutate(saveStatus);
  };

  const isLoadingAny = schemaLoading || (!isNew && (recordLoading || !loaded));
  if (isLoadingAny) {
    return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>;
  }
  if (schemaError || !collection) {
    return <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load the collection schema.</div>;
  }
  if (!isNew && recordError) {
    return <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load the record.</div>;
  }

  const title = typeof data[titleKey] === 'string' && (data[titleKey] as string).trim()
    ? (data[titleKey] as string)
    : isNew ? `New ${collection.name.replace(/s$/i, '').toLowerCase()}` : record?.title ?? 'Record';

  return (
    <div className="max-w-3xl mx-auto">
      {/* Header */}
      <div
        className="flex items-center gap-3 mb-6"
        onKeyDown={(e) => {
          if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); save(status); }
        }}
      >
        <Link to={`/sites/${siteId}/collections/${collectionId}/records`} className="btn btn-ghost btn-sm btn-square text-base-content/40">
          <ArrowLeft size={16} />
        </Link>
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-bold text-base-content truncate">{title}</h1>
          <p className="text-[13px] text-base-content/50 flex items-center gap-2">
            {collection.icon ? `${collection.icon} ` : ''}{collection.name}
            <StatusBadge status={status} />
          </p>
        </div>
        <button
          onClick={() => save('draft')}
          disabled={saveMutation.isPending}
          className="btn btn-ghost btn-sm gap-1.5 text-[12px] border border-base-300/40"
        >
          {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />}
          Save draft
        </button>
        <button
          onClick={() => save('published')}
          disabled={saveMutation.isPending}
          className="btn btn-primary btn-sm gap-1.5 text-[12px]"
        >
          {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Rocket size={13} />}
          Save & publish
        </button>
      </div>

      {fields.length === 0 && (
        <div className="border border-warning/40 bg-warning/10 rounded-box p-4 text-[13px] text-base-content/70">
          This collection has no fields yet —{' '}
          <Link to={`/sites/${siteId}/collections/${collectionId}/schema`} className="text-primary hover:underline">define the schema</Link> first.
        </div>
      )}

      {/* Form */}
      {fields.length > 0 && (
        <div className="border border-base-300/40 rounded-box bg-base-100 p-5 space-y-5">
          {fields.map((f) =>
            f.type === 'relation' ? (
              <RelationInput
                key={f.key}
                siteId={siteId}
                field={f}
                value={relations[f.key] ?? []}
                onChange={(v) => setRelationValue(f.key, v)}
                error={errors[`relations.${f.key}`]}
              />
            ) : (
              <FieldInput
                key={f.key}
                siteId={siteId}
                field={f}
                value={data[f.key]}
                onChange={(v) => setFieldValue(f.key, v)}
                error={errors[`data.${f.key}`]}
              />
            ),
          )}

          {/* Slug + status */}
          <div className="border-t border-base-300/20 pt-4 grid grid-cols-12 gap-4">
            <div className="col-span-12 sm:col-span-8">
              <label className="text-[12px] text-base-content/60 mb-1 block">Slug</label>
              <input
                value={slug}
                onChange={(e) => { setSlug(e.target.value); setSlugTouched(true); }}
                placeholder="auto-generated from the title"
                className={`input input-bordered input-sm w-full text-[13px] font-mono ${errors.slug ? 'input-error' : ''}`}
              />
              {errors.slug
                ? <p className="text-[11px] text-error mt-1">{errors.slug}</p>
                : <p className="text-[11px] text-base-content/30 mt-1">Part of the record’s public URL.</p>}
            </div>
            <div className="col-span-12 sm:col-span-4">
              <label className="text-[12px] text-base-content/60 mb-1 block">Status</label>
              <label className="flex items-center gap-2.5 cursor-pointer h-8">
                <input
                  type="checkbox"
                  className="toggle toggle-sm toggle-success"
                  checked={status === 'published'}
                  onChange={(e) => setStatus(e.target.checked ? 'published' : 'draft')}
                />
                <span className="text-[13px] text-base-content/60">{status === 'published' ? 'Published' : 'Draft'}</span>
              </label>
            </div>
          </div>

          {/* Footer save (long forms) */}
          <div className="flex justify-end gap-2 border-t border-base-300/20 pt-4">
            <button onClick={() => save('draft')} disabled={saveMutation.isPending}
              className="btn btn-ghost btn-sm gap-1.5 text-[12px] border border-base-300/40">
              {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />}
              Save draft
            </button>
            <button onClick={() => save('published')} disabled={saveMutation.isPending}
              className="btn btn-primary btn-sm gap-1.5 text-[12px]">
              {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Rocket size={13} />}
              Save & publish
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
