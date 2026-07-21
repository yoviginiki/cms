import { useCallback, useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Loader2, Save, Rocket, History, CalendarClock, Search as SearchIcon, X, ChevronDown, ChevronRight } from 'lucide-react';
import {
  collections, collectionRecords,
  type Collection, type CollectionRecord, type CollectionRecordPayload, type CollectionRecordRevision,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { slugify } from '@/lib/slugify';
import { apiErr, validationErrors } from './shared';
import { FieldInput, RelationInput, SingleAssetInput, type SelectedRelation } from './RecordFields';

/** ISO timestamp → value for <input type="datetime-local"> in the browser's zone. */
function toLocalInput(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** datetime-local value → ISO string, or null when cleared. */
function localToIso(local: string): string | null {
  if (!local) return null;
  const d = new Date(local);
  return Number.isNaN(d.getTime()) ? null : d.toISOString();
}

function relTime(iso: string): string {
  const s = (Date.now() - new Date(iso).getTime()) / 1000;
  if (s < 60) return 'just now';
  if (s < 3600) return `${Math.floor(s / 60)}m ago`;
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
  if (s < 7 * 86400) return `${Math.floor(s / 86400)}d ago`;
  return new Date(iso).toLocaleDateString();
}

const REVISION_BADGE: Record<CollectionRecordRevision['event'], string> = {
  created: 'badge-success',
  updated: 'badge-ghost',
  restored: 'badge-warning',
};

/** Collapsible side-panel section; defaultOpen is read once on mount (post-hydration). */
function CollapseSection({ title, icon, badge, defaultOpen, children }: {
  title: string;
  icon: React.ReactNode;
  badge?: React.ReactNode;
  defaultOpen: boolean;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border border-base-300/30 rounded-box bg-base-200/20">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="w-full flex items-center gap-2 px-4 py-2.5 text-[12px] font-medium text-base-content/70 uppercase tracking-wider"
      >
        {open ? <ChevronDown size={13} className="text-base-content/40" /> : <ChevronRight size={13} className="text-base-content/40" />}
        {icon}
        {title}
        {badge}
      </button>
      {open && <div className="px-4 pb-4">{children}</div>}
    </div>
  );
}

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
  const [publishAt, setPublishAt] = useState('');    // datetime-local; '' = null
  const [unpublishAt, setUnpublishAt] = useState('');
  const [seoTitle, setSeoTitle] = useState('');
  const [seoDescription, setSeoDescription] = useState('');
  const [seoOgImage, setSeoOgImage] = useState('');
  const [historyOpen, setHistoryOpen] = useState(false);
  const [restoreTarget, setRestoreTarget] = useState<CollectionRecordRevision | null>(null);

  /** Load form state from a serialized record (initial load and revision restore). */
  const hydrate = useCallback((rec: CollectionRecord) => {
    setData(rec.data ?? {});
    setSlug(rec.slug ?? '');
    setSlugTouched(true); // existing slugs shouldn't silently change with the title
    setStatus(rec.status);
    setPublishAt(toLocalInput(rec.publish_at));
    setUnpublishAt(toLocalInput(rec.unpublish_at));
    setSeoTitle(rec.seo_meta?.title ?? '');
    setSeoDescription(rec.seo_meta?.description ?? '');
    setSeoOgImage(rec.seo_meta?.og_image ?? '');
    const rels: Record<string, SelectedRelation[]> = {};
    for (const [key, items] of Object.entries(rec.relations ?? {})) {
      rels[key] = [...items]
        .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
        .map((it) => ({ id: it.id, title: it.title, pivot: (it.pivot as Record<string, unknown>) ?? {} }));
    }
    setRelations(rels);
    setLoaded(true);
  }, []);

  // Hydrate form from the loaded record (edit) or defaults (new)
  useEffect(() => {
    if (isNew) { setLoaded(true); return; }
    if (!record) return;
    hydrate(record);
  }, [record, isNew, hydrate]);

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
    publish_at: localToIso(publishAt),
    unpublish_at: localToIso(unpublishAt),
    seo_meta: {
      ...(seoTitle.trim() ? { title: seoTitle.trim() } : {}),
      ...(seoDescription.trim() ? { description: seoDescription.trim() } : {}),
      ...(seoOgImage ? { og_image: seoOgImage } : {}),
    },
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

  // ── Revision history (edit mode only) ──
  const { data: revisions = [], isLoading: revisionsLoading } = useQuery<CollectionRecordRevision[]>({
    queryKey: ['collection-record-revisions', siteId, collectionId, recordId],
    queryFn: () => collectionRecords.revisions(siteId, collectionId, recordId!).then((r) => r.data.data),
    enabled: !isNew && historyOpen,
  });

  const restoreMutation = useMutation({
    mutationFn: (revisionId: string) => collectionRecords.restoreRevision(siteId, collectionId, recordId!, revisionId),
    onSuccess: (res) => {
      hydrate(res.data.data);
      queryClient.invalidateQueries({ queryKey: ['collection-record', siteId, collectionId, recordId] });
      queryClient.invalidateQueries({ queryKey: ['collection-records', siteId, collectionId] });
      queryClient.invalidateQueries({ queryKey: ['collection-record-revisions', siteId, collectionId, recordId] });
      setHistoryOpen(false);
      toast({ type: 'success', message: 'Revision restored.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
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
        {!isNew && (
          <button
            onClick={() => setHistoryOpen(true)}
            className="btn btn-ghost btn-sm gap-1.5 text-[12px] text-base-content/60"
            title="Revision history"
          >
            <History size={13} /> History
          </button>
        )}
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

          {/* Scheduling */}
          <CollapseSection
            title="Scheduling"
            icon={<CalendarClock size={13} className="text-base-content/40" />}
            badge={(publishAt || unpublishAt) ? <span className="badge badge-info badge-outline badge-xs text-[10px] normal-case">active</span> : null}
            defaultOpen={!!(publishAt || unpublishAt)}
          >
              <div className="grid grid-cols-12 gap-4 pt-1">
                <div className="col-span-12 sm:col-span-6">
                  <label className="text-[12px] text-base-content/60 mb-1 block">Publish at</label>
                  <input
                    type="datetime-local"
                    value={publishAt}
                    onChange={(e) => setPublishAt(e.target.value)}
                    className={`input input-bordered input-sm w-full text-[13px] ${errors.publish_at ? 'input-error' : ''}`}
                  />
                  {errors.publish_at
                    ? <p className="text-[11px] text-error mt-1">{errors.publish_at}</p>
                    : <p className="text-[11px] text-base-content/30 mt-1">Goes live automatically at this time. Empty = immediately.</p>}
                </div>
                <div className="col-span-12 sm:col-span-6">
                  <label className="text-[12px] text-base-content/60 mb-1 block">Unpublish at</label>
                  <input
                    type="datetime-local"
                    value={unpublishAt}
                    onChange={(e) => setUnpublishAt(e.target.value)}
                    className={`input input-bordered input-sm w-full text-[13px] ${errors.unpublish_at ? 'input-error' : ''}`}
                  />
                  {errors.unpublish_at
                    ? <p className="text-[11px] text-error mt-1">{errors.unpublish_at}</p>
                    : <p className="text-[11px] text-base-content/30 mt-1">Reverts to draft at this time. Empty = never expires.</p>}
                </div>
              </div>
          </CollapseSection>

          {/* SEO */}
          <CollapseSection
            title="SEO"
            icon={<SearchIcon size={13} className="text-base-content/40" />}
            defaultOpen={!!(seoTitle || seoDescription || seoOgImage)}
          >
              <div className="space-y-4 pt-1">
                <div>
                  <label className="text-[12px] text-base-content/60 mb-1 block">Meta title</label>
                  <input
                    value={seoTitle}
                    onChange={(e) => setSeoTitle(e.target.value)}
                    placeholder="Defaults to the record title"
                    className="input input-bordered input-sm w-full text-[13px]"
                  />
                </div>
                <div>
                  <div className="flex items-baseline justify-between mb-1">
                    <label className="text-[12px] text-base-content/60">Meta description</label>
                    <span className={`text-[11px] tabular-nums ${seoDescription.length > 300 ? 'text-error' : 'text-base-content/30'}`}>
                      {seoDescription.length}/300
                    </span>
                  </div>
                  <textarea
                    value={seoDescription}
                    onChange={(e) => setSeoDescription(e.target.value)}
                    maxLength={300}
                    rows={3}
                    placeholder="Short summary shown in search results"
                    className="textarea textarea-bordered w-full text-[13px] leading-relaxed"
                  />
                </div>
                <div>
                  <label className="text-[12px] text-base-content/60 mb-1 block">Social share image (og:image)</label>
                  <SingleAssetInput
                    siteId={siteId}
                    value={seoOgImage}
                    onChange={(assetId) => setSeoOgImage(assetId)}
                    accept="image"
                  />
                </div>
              </div>
          </CollapseSection>

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

      {/* History drawer */}
      {historyOpen && (
        <>
          <div className="fixed inset-0 bg-black/30 z-40" onClick={() => setHistoryOpen(false)} />
          <div className="fixed inset-y-0 right-0 w-full max-w-md bg-base-100 border-l border-base-300/50 z-50 flex flex-col shadow-xl">
            <div className="flex items-center justify-between px-4 py-3 border-b border-base-300/30">
              <h3 className="text-[13px] font-medium text-base-content flex items-center gap-2">
                <History size={14} className="text-base-content/50" /> Revision history
              </h3>
              <button onClick={() => setHistoryOpen(false)} className="btn btn-ghost btn-xs btn-square" aria-label="Close">
                <X size={14} />
              </button>
            </div>
            <div className="flex-1 overflow-y-auto">
              {revisionsLoading && (
                <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-base-content/30" /></div>
              )}
              {!revisionsLoading && revisions.length === 0 && (
                <p className="py-16 text-center text-[13px] text-base-content/35">No revisions yet — they appear after the next save.</p>
              )}
              <div className="divide-y divide-base-300/20">
                {revisions.map((rev) => (
                  <div key={rev.id} className="px-4 py-3 flex items-start gap-3">
                    <span className={`badge badge-xs badge-outline text-[10px] mt-0.5 ${REVISION_BADGE[rev.event]}`}>{rev.event}</span>
                    <div className="flex-1 min-w-0">
                      <div className="text-[13px] text-base-content/80 truncate">{rev.title || <em className="text-base-content/35">untitled</em>}</div>
                      <div className="text-[11px] text-base-content/40">
                        {rev.user || 'unknown'} · {relTime(rev.created_at)} · <StatusBadge status={rev.status} />
                      </div>
                    </div>
                    <button
                      onClick={() => setRestoreTarget(rev)}
                      disabled={restoreMutation.isPending}
                      className="btn btn-ghost btn-xs gap-1 text-[11px] text-primary"
                    >
                      {restoreMutation.isPending && restoreMutation.variables === rev.id
                        ? <Loader2 size={11} className="animate-spin" /> : null}
                      Restore
                    </button>
                  </div>
                ))}
              </div>
            </div>
            <p className="px-4 py-2.5 border-t border-base-300/30 text-[11px] text-base-content/30">Up to the last 20 changes, newest first.</p>
          </div>
        </>
      )}
      <ConfirmDialog
        open={!!restoreTarget}
        title="Restore this revision"
        message={`Replace the current content with the "${restoreTarget?.event}" revision from ${restoreTarget ? relTime(restoreTarget.created_at) : ''} ("${restoreTarget?.title || 'untitled'}")? The current state is kept as its own revision.`}
        confirmText="Restore"
        variant="warning"
        onConfirm={() => restoreTarget && restoreMutation.mutate(restoreTarget.id)}
        onClose={() => setRestoreTarget(null)}
      />
    </div>
  );
}
