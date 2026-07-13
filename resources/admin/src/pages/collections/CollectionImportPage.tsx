import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  Upload, ArrowLeft, ArrowRight, Loader2, CheckCircle, AlertTriangle, FileSpreadsheet, Table2,
} from 'lucide-react';
import { collections, collectionImport, type Collection } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { apiErr } from './shared';

// ─────────────────────────────────────────────────────────────────────────────
// CSV / Excel import wizard: upload → map columns to fields → run with live
// progress (poll every 1.5s) → per-row error report.
// ─────────────────────────────────────────────────────────────────────────────

type Step = 'upload' | 'mapping' | 'running';

interface UploadInfo {
  import_id: string;
  filename: string;
  headers: string[];
  preview_rows: string[][];
}

interface ImportStatus {
  status: 'uploaded' | 'queued' | 'running' | 'completed' | 'failed';
  message?: string;
  step?: string;
  progress?: number;
  counts?: Record<string, number>;
  result?: {
    created: number;
    updated: number;
    failed: number;
    total: number;
    errors: { row: number; message: string }[];
  } | null;
  error?: string | null;
}

const STEPS: { key: Step; label: string }[] = [
  { key: 'upload', label: 'Upload' },
  { key: 'mapping', label: 'Map columns' },
  { key: 'running', label: 'Import' },
];

export default function CollectionImportPage() {
  const { siteId = '', collectionId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [step, setStep] = useState<Step>('upload');
  const [isDragging, setIsDragging] = useState(false);
  const [upload, setUpload] = useState<UploadInfo | null>(null);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [mode, setMode] = useState<'insert' | 'upsert'>('insert');
  const [keyField, setKeyField] = useState('');
  const [errorPolicy, setErrorPolicy] = useState<'skip' | 'halt'>('skip');
  const [defaultStatus, setDefaultStatus] = useState<'draft' | 'published'>('draft');
  const [createMissingRelations, setCreateMissingRelations] = useState(false);
  const [status, setStatus] = useState<ImportStatus | null>(null);

  const { data: collection, isLoading: collectionLoading, error: collectionError } = useQuery<Collection>({
    queryKey: ['collection', siteId, collectionId],
    queryFn: () => collections.get(siteId, collectionId).then((r) => r.data.data),
  });

  const fields = collection?.schema?.fields ?? [];
  const uniqueFields = fields.filter((f) => f.unique);

  const uploadMutation = useMutation({
    mutationFn: (file: File) => collectionImport.upload(siteId, collectionId, file),
    onSuccess: (res) => {
      const info: UploadInfo = res.data.data;
      setUpload(info);
      // Pre-match columns by header ≈ field key or label (case-insensitive)
      const initial: Record<string, string> = {};
      info.headers.forEach((h, i) => {
        const norm = h.trim().toLowerCase();
        const match = fields.find(
          (f) => f.key.toLowerCase() === norm || f.label.trim().toLowerCase() === norm,
        );
        initial[String(i)] = match?.key ?? '';
      });
      setMapping(initial);
      setStep('mapping');
    },
  });

  const executeMutation = useMutation({
    mutationFn: () =>
      collectionImport.execute(siteId, collectionId, upload!.import_id, {
        mapping,
        mode,
        ...(mode === 'upsert' ? { key_field: keyField } : {}),
        error_policy: errorPolicy,
        status: defaultStatus,
        create_missing_relations: createMissingRelations,
      }),
    onSuccess: () => {
      setStatus(null);
      setStep('running');
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  // Poll import status every 1.5s while running
  useEffect(() => {
    if (step !== 'running' || !upload) return;
    if (status?.status === 'completed' || status?.status === 'failed') return;

    const interval = setInterval(async () => {
      try {
        const res = await collectionImport.status(siteId, collectionId, upload.import_id);
        const s: ImportStatus = res.data.data;
        setStatus(s);
        if (s.status === 'completed' || s.status === 'failed') {
          clearInterval(interval);
          queryClient.invalidateQueries({ queryKey: ['collection-records', siteId, collectionId] });
          queryClient.invalidateQueries({ queryKey: ['collections', siteId] });
        }
      } catch {
        // transient — keep polling
      }
    }, 1500);
    return () => clearInterval(interval);
  }, [step, upload, status?.status, siteId, collectionId, queryClient]);

  const handleFiles = useCallback((files: FileList | null) => {
    if (!files || files.length === 0) return;
    uploadMutation.mutate(files[0]);
  }, [uploadMutation]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  }, [handleFiles]);

  const mappedCount = Object.values(mapping).filter(Boolean).length;
  const duplicateTargets = useMemo(() => {
    const seen = new Map<string, number>();
    for (const v of Object.values(mapping)) {
      if (v) seen.set(v, (seen.get(v) ?? 0) + 1);
    }
    return [...seen.entries()].filter(([, n]) => n > 1).map(([k]) => k);
  }, [mapping]);
  const keyFieldMapped = !keyField || Object.values(mapping).includes(keyField);
  const canExecute =
    mappedCount > 0 &&
    duplicateTargets.length === 0 &&
    (mode === 'insert' || (!!keyField && keyFieldMapped));

  if (collectionLoading) {
    return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>;
  }
  if (collectionError || !collection) {
    return <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load the collection.</div>;
  }

  const stepIdx = STEPS.findIndex((s) => s.key === step);

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <Link to={`/sites/${siteId}/collections/${collectionId}/records`} className="btn btn-ghost btn-sm btn-square text-base-content/40">
          <ArrowLeft size={16} />
        </Link>
        <div>
          <h1 className="text-xl font-bold text-base-content">Import into {collection.name}</h1>
          <p className="text-[13px] text-base-content/50">CSV or Excel, up to 50 MB</p>
        </div>
      </div>

      {/* Step indicator */}
      <div className="flex items-center gap-2 mb-6">
        {STEPS.map((s, i) => {
          const isActive = i === stepIdx;
          const isComplete = i < stepIdx;
          return (
            <div key={s.key} className="flex items-center gap-2">
              {i > 0 && <div className={`w-8 h-px ${isComplete ? 'bg-primary' : 'bg-base-300/50'}`} />}
              <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-medium ${
                isActive ? 'bg-primary/15 text-primary' :
                isComplete ? 'bg-primary text-primary-content' :
                'bg-base-300/30 text-base-content/35'
              }`}>
                {isComplete && <CheckCircle size={11} />}
                {s.label}
              </div>
            </div>
          );
        })}
      </div>

      {/* Step 1: upload */}
      {step === 'upload' && (
        <div
          onDrop={handleDrop}
          onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
          onDragLeave={(e) => { e.preventDefault(); setIsDragging(false); }}
          className={`rounded-box border-2 border-dashed p-12 text-center transition-colors ${
            isDragging ? 'border-primary bg-primary/5' : 'border-base-300/50 hover:border-base-300'
          }`}
        >
          <FileSpreadsheet className={`h-12 w-12 mx-auto mb-4 ${isDragging ? 'text-primary' : 'text-base-content/20'}`} strokeWidth={1.5} />
          <h3 className="text-base font-medium text-base-content mb-2">Upload a spreadsheet</h3>
          <p className="text-[13px] text-base-content/45 mb-4">
            Drag & drop a .csv or .xlsx file here, or click to browse.<br />
            The first row should contain column headers.
          </p>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={uploadMutation.isPending}
            className="btn btn-primary btn-sm gap-1.5 text-[12px]"
          >
            {uploadMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Upload size={13} />}
            {uploadMutation.isPending ? 'Uploading…' : 'Choose file'}
          </button>
          <input
            ref={fileInputRef}
            type="file"
            accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            className="hidden"
            onChange={(e) => { handleFiles(e.target.files); e.target.value = ''; }}
          />
          {uploadMutation.isError && (
            <div className="mt-4 border border-error/30 bg-error/10 rounded-box p-3 text-[13px] text-error max-w-md mx-auto">
              {apiErr(uploadMutation.error)}
            </div>
          )}
        </div>
      )}

      {/* Step 2: mapping */}
      {step === 'mapping' && upload && (
        <div className="space-y-5">
          <div className="border border-base-300/40 rounded-box bg-base-100">
            <div className="px-4 py-2.5 border-b border-base-300/30 flex items-center justify-between">
              <h3 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Map columns → fields</h3>
              <span className="text-[12px] text-base-content/40">{upload.filename} · {mappedCount}/{upload.headers.length} mapped</span>
            </div>
            <div className="overflow-x-auto">
              <table className="table table-sm">
                <thead>
                  <tr className="border-b border-base-300/40">
                    <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">File column</th>
                    <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider">Sample values</th>
                    <th className="text-[11px] font-medium text-base-content/40 uppercase tracking-wider w-56">Import into</th>
                  </tr>
                </thead>
                <tbody>
                  {upload.headers.map((h, i) => {
                    const target = mapping[String(i)] ?? '';
                    const dup = target && duplicateTargets.includes(target);
                    return (
                      <tr key={i} className="border-b border-base-300/20">
                        <td className="text-[13px] font-medium text-base-content/80">{h || <em className="text-base-content/30">column {i + 1}</em>}</td>
                        <td className="text-[12px] text-base-content/40">
                          {upload.preview_rows.slice(0, 3).map((row) => row[i]).filter((v) => v !== undefined && v !== '').slice(0, 3).join(' · ') || '—'}
                        </td>
                        <td>
                          <select
                            value={target}
                            onChange={(e) => setMapping((prev) => ({ ...prev, [String(i)]: e.target.value }))}
                            className={`select select-bordered select-xs w-full text-[12px] ${dup ? 'select-error' : ''}`}
                          >
                            <option value="">— ignore —</option>
                            {fields.map((f) => (
                              <option key={f.key} value={f.key}>{f.label} ({f.key})</option>
                            ))}
                          </select>
                          {dup && <p className="text-[10px] text-error mt-0.5">Mapped twice</p>}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>

          {/* Options */}
          <div className="border border-base-300/40 rounded-box bg-base-100 p-4 grid grid-cols-12 gap-4">
            <div className="col-span-12 sm:col-span-6">
              <label className="text-[11px] text-base-content/50 mb-1.5 block">Mode</label>
              <div className="flex flex-col gap-1.5">
                <label className="flex items-center gap-2 text-[13px] text-base-content/80 cursor-pointer">
                  <input type="radio" className="radio radio-xs radio-primary" checked={mode === 'insert'} onChange={() => setMode('insert')} />
                  Insert — every row becomes a new record
                </label>
                <label className="flex items-center gap-2 text-[13px] text-base-content/80 cursor-pointer">
                  <input type="radio" className="radio radio-xs radio-primary" checked={mode === 'upsert'} onChange={() => setMode('upsert')} />
                  Upsert — update records matched by a unique field
                </label>
              </div>
              {mode === 'upsert' && (
                <div className="mt-2">
                  <select value={keyField} onChange={(e) => setKeyField(e.target.value)}
                    className={`select select-bordered select-xs w-full max-w-xs text-[12px] ${!keyFieldMapped || !keyField ? 'select-warning' : ''}`}>
                    <option value="">— pick the match key —</option>
                    {uniqueFields.map((f) => <option key={f.key} value={f.key}>{f.label} ({f.key})</option>)}
                  </select>
                  {uniqueFields.length === 0 && <p className="text-[11px] text-warning mt-1">No unique fields in the schema — upsert needs one.</p>}
                  {keyField && !keyFieldMapped && <p className="text-[11px] text-error mt-1">The match key must also be mapped to a column above.</p>}
                </div>
              )}
            </div>
            <div className="col-span-6 sm:col-span-3">
              <label className="text-[11px] text-base-content/50 mb-1.5 block">On row errors</label>
              <div className="flex flex-col gap-1.5">
                <label className="flex items-center gap-2 text-[13px] text-base-content/80 cursor-pointer">
                  <input type="radio" className="radio radio-xs radio-primary" checked={errorPolicy === 'skip'} onChange={() => setErrorPolicy('skip')} />
                  Skip the row
                </label>
                <label className="flex items-center gap-2 text-[13px] text-base-content/80 cursor-pointer">
                  <input type="radio" className="radio radio-xs radio-primary" checked={errorPolicy === 'halt'} onChange={() => setErrorPolicy('halt')} />
                  Halt the import
                </label>
              </div>
            </div>
            <div className="col-span-6 sm:col-span-3">
              <label className="text-[11px] text-base-content/50 mb-1.5 block">Imported records are</label>
              <select value={defaultStatus} onChange={(e) => setDefaultStatus(e.target.value as 'draft' | 'published')}
                className="select select-bordered select-xs w-full text-[12px]">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
              <label className="flex items-center gap-2 text-[12px] text-base-content/60 cursor-pointer mt-2.5" title="When a relation column references a record that doesn't exist yet, create it on the fly">
                <input type="checkbox" className="checkbox checkbox-xs" checked={createMissingRelations} onChange={(e) => setCreateMissingRelations(e.target.checked)} />
                Create missing related records
              </label>
            </div>
          </div>

          {/* Preview */}
          <div className="border border-base-300/40 rounded-box bg-base-100">
            <div className="px-4 py-2.5 border-b border-base-300/30">
              <h3 className="text-[12px] font-medium text-base-content/70 uppercase tracking-wider">Preview — first {Math.min(20, upload.preview_rows.length)} rows</h3>
            </div>
            <div className="overflow-x-auto max-h-72 overflow-y-auto">
              <table className="table table-xs">
                <thead>
                  <tr className="border-b border-base-300/40">
                    {upload.headers.map((h, i) => (
                      <th key={i} className="text-[10px] font-medium text-base-content/40 uppercase tracking-wider whitespace-nowrap">
                        {h || `col ${i + 1}`}
                        {mapping[String(i)] && <span className="block text-primary normal-case font-normal">→ {mapping[String(i)]}</span>}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {upload.preview_rows.slice(0, 20).map((row, ri) => (
                    <tr key={ri} className="border-b border-base-300/10">
                      {upload.headers.map((_, ci) => (
                        <td key={ci} className={`text-[12px] whitespace-nowrap max-w-40 truncate ${mapping[String(ci)] ? 'text-base-content/70' : 'text-base-content/25'}`}>
                          {row[ci] ?? ''}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="flex items-center justify-between">
            <button onClick={() => { setStep('upload'); setUpload(null); }} className="btn btn-ghost btn-sm gap-1.5 text-[12px]">
              <ArrowLeft size={13} /> Back
            </button>
            <div className="flex items-center gap-3">
              {mappedCount === 0 && <span className="text-[12px] text-base-content/35">Map at least one column to continue.</span>}
              <button
                onClick={() => executeMutation.mutate()}
                disabled={!canExecute || executeMutation.isPending}
                className="btn btn-primary btn-sm gap-1.5 text-[12px]"
              >
                {executeMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <ArrowRight size={13} />}
                Start import
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Step 3: running / result */}
      {step === 'running' && (
        <div className="border border-base-300/40 rounded-box bg-base-100 overflow-hidden">
          {(!status || status.status === 'uploaded' || status.status === 'queued' || status.status === 'running') && (
            <>
              <div className="px-5 py-3.5 border-b border-base-300/30 flex items-center gap-2.5">
                <Loader2 size={16} className="animate-spin text-primary" />
                <h3 className="text-sm font-medium text-base-content">Importing…</h3>
              </div>
              <div className="p-5">
                <div className="flex items-center justify-between mb-1.5">
                  <span className="text-[13px] text-base-content/60">{status?.message || status?.step || 'Waiting for the worker to pick up the job…'}</span>
                  <span className="text-[13px] font-semibold text-primary tabular-nums">{status?.progress ?? 0}%</span>
                </div>
                <progress className="progress progress-primary w-full h-2" value={status?.progress ?? 0} max={100} />
                {status?.counts && Object.keys(status.counts).length > 0 && (
                  <div className="flex gap-4 mt-3 text-[12px] text-base-content/50">
                    {Object.entries(status.counts).map(([k, v]) => (
                      <span key={k} className="tabular-nums">{v} {k}</span>
                    ))}
                  </div>
                )}
                <p className="mt-5 text-[11px] text-base-content/30 text-center">Keep this page open — large files can take a few minutes.</p>
              </div>
            </>
          )}

          {status && (status.status === 'completed' || status.status === 'failed') && (
            <>
              <div className={`px-5 py-3.5 border-b flex items-center gap-2.5 ${
                status.status === 'completed' ? 'border-success/20 bg-success/10' : 'border-error/20 bg-error/10'
              }`}>
                {status.status === 'completed'
                  ? <CheckCircle size={16} className="text-success" />
                  : <AlertTriangle size={16} className="text-error" />}
                <h3 className={`text-sm font-medium ${status.status === 'completed' ? 'text-success' : 'text-error'}`}>
                  {status.status === 'completed' ? 'Import complete' : 'Import failed'}
                </h3>
              </div>
              <div className="p-5">
                {status.error && (
                  <div className="border border-error/30 bg-error/10 rounded-box p-3 text-[13px] text-error mb-4">{status.error}</div>
                )}
                {status.result && (
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                    {([
                      ['Created', status.result.created],
                      ['Updated', status.result.updated],
                      ['Failed', status.result.failed],
                      ['Total rows', status.result.total],
                    ] as [string, number][]).map(([label, n]) => (
                      <div key={label} className="border border-base-300/30 rounded-box p-3 text-center bg-base-200/30">
                        <p className={`text-xl font-bold tabular-nums ${label === 'Failed' && n > 0 ? 'text-error' : 'text-base-content'}`}>{n}</p>
                        <p className="text-[11px] text-base-content/40">{label}</p>
                      </div>
                    ))}
                  </div>
                )}
                {(status.result?.errors?.length ?? 0) > 0 && (
                  <div className="border border-base-300/40 rounded-box overflow-hidden mb-5">
                    <div className="px-3 py-2 border-b border-base-300/30 bg-base-200/30 text-[12px] font-medium text-base-content/60">
                      Row errors ({status.result!.errors.length})
                    </div>
                    <div className="max-h-56 overflow-y-auto">
                      <table className="table table-xs">
                        <tbody>
                          {status.result!.errors.map((e, i) => (
                            <tr key={i} className="border-b border-base-300/10">
                              <td className="w-20 text-[12px] text-base-content/40 tabular-nums">row {e.row}</td>
                              <td className="text-[12px] text-error/90">{e.message}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
                <div className="flex gap-2">
                  <button
                    onClick={() => navigate(`/sites/${siteId}/collections/${collectionId}/records`)}
                    className="btn btn-primary btn-sm gap-1.5 text-[12px]"
                  >
                    <Table2 size={13} /> Back to records
                  </button>
                  <button
                    onClick={() => { setStep('upload'); setUpload(null); setStatus(null); }}
                    className="btn btn-ghost btn-sm gap-1.5 text-[12px] border border-base-300/40"
                  >
                    <Upload size={13} /> Import another file
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
