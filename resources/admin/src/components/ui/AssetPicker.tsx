import { useState, useRef, useCallback, useEffect, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Upload, X, Search, Image, Film, Music, File, Check, Loader2, Folder, FolderPlus, FolderOpen, Trash2, ChevronRight } from 'lucide-react';
import { assets, assetFolders, type AssetFolderInfo } from '@/lib/api';

export interface PickedAsset {
  id: string;
  url: string;
  filename: string;
  mime_type: string;
  alt_text?: string | null;
  width?: number | null;
  height?: number | null;
}

interface Asset {
  id: string;
  filename: string;
  original_name: string;
  mime_type: string;
  size: number;
  file_size?: number;
  url: string;
  folder: string | null;
  alt_text?: string | null;
  dimensions?: { width?: number; height?: number } | null;
  created_at: string;
}

interface AssetPickerProps {
  open: boolean;
  onClose: () => void;
  /** Single-select: called as soon as a file is clicked or uploaded. */
  onSelect: (asset: PickedAsset) => void;
  /** Multi-select: enables checkboxes + multi-upload; "Add N files" calls this. */
  onSelectMany?: (assets: PickedAsset[]) => void;
  multiple?: boolean;
  accept?: 'image' | 'video' | 'audio' | 'all';
  currentUrl?: string;
  /** URLs already used by the caller (multi mode) — shown as "in use" so they aren't added twice. */
  excludeUrls?: string[];
}

function formatSize(bytes: number): string {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1048576).toFixed(1)} MB`;
}

function getIcon(mime: string) {
  if (mime.startsWith('image/')) return Image;
  if (mime.startsWith('video/')) return Film;
  if (mime.startsWith('audio/')) return Music;
  return File;
}

function toPicked(a: Asset): PickedAsset {
  return {
    id: a.id, url: a.url, filename: a.original_name || a.filename, mime_type: a.mime_type,
    alt_text: a.alt_text ?? null, width: a.dimensions?.width ?? null, height: a.dimensions?.height ?? null,
  };
}

/** Folder scope: ALL = every file regardless of folder; '' = root only; 'a/b' = that folder. */
const ALL = '__all__';

/**
 * Media library modal: folders (create / delete), upload into the current
 * folder (single or many), search, single- or multi-select.
 */
export function AssetPicker({ open, onClose, onSelect, onSelectMany, multiple = false, accept = 'all', currentUrl, excludeUrls }: AssetPickerProps) {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [search, setSearch] = useState('');
  const [isDragging, setIsDragging] = useState(false);
  const [folder, setFolder] = useState<string>(ALL);
  const [newFolderOpen, setNewFolderOpen] = useState(false);
  const [newFolderName, setNewFolderName] = useState('');
  const [selected, setSelected] = useState<Map<string, PickedAsset>>(new Map());
  const [uploadProgress, setUploadProgress] = useState<{ done: number; total: number; errors: string[] } | null>(null);

  useEffect(() => { if (open) { setSelected(new Map()); setSearch(''); } }, [open]);

  const typeFilter = accept === 'all' ? undefined : accept;

  const { data: folderData } = useQuery({
    queryKey: ['asset-folders', siteId],
    queryFn: () => assetFolders.list(siteId).then(r => r.data),
    enabled: open,
  });
  const folders: AssetFolderInfo[] = folderData?.data ?? [];

  const { data: assetList, isLoading } = useQuery<Asset[]>({
    queryKey: ['assets-picker', siteId, typeFilter, folder],
    queryFn: () => {
      const params: Record<string, unknown> = { per_page: 500 };
      if (typeFilter) params.type = typeFilter;
      if (folder !== ALL) params.folder = folder;
      return assets.list(siteId, params).then((r: any) => r.data.data);
    },
    enabled: open,
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['assets-picker', siteId] });
    queryClient.invalidateQueries({ queryKey: ['assets', siteId] });
    queryClient.invalidateQueries({ queryKey: ['asset-folders', siteId] });
  };

  const uploadFolder = folder === ALL ? '' : folder;

  // Sequential multi-upload so the server isn't hammered and progress is legible.
  const uploadMutation = useMutation({
    mutationFn: async (files: File[]) => {
      const out: PickedAsset[] = [];
      const errors: string[] = [];
      setUploadProgress({ done: 0, total: files.length, errors });
      for (let i = 0; i < files.length; i++) {
        try {
          const r: any = await assets.upload(siteId, files[i], uploadFolder);
          out.push(toPicked(r.data.data));
        } catch (e: any) {
          errors.push(`${files[i].name}: ${e?.response?.data?.message || e?.response?.data?.errors?.file?.[0] || 'upload failed'}`);
        }
        setUploadProgress({ done: i + 1, total: files.length, errors: [...errors] });
      }
      return out;
    },
    onSuccess: (uploaded) => {
      invalidate();
      if (!multiple) {
        setUploadProgress(null);
        if (uploaded[0]) onSelect(uploaded[0]);
        return;
      }
      setSelected(prev => {
        const next = new Map(prev);
        uploaded.forEach(a => next.set(a.id, a));
        return next;
      });
      setUploadProgress(p => p && p.errors.length ? p : null);
    },
  });

  const createFolder = useMutation({
    mutationFn: (name: string) => assetFolders.create(siteId, name, folder === ALL ? null : folder),
    onSuccess: (r) => { invalidate(); setNewFolderOpen(false); setNewFolderName(''); setFolder(r.data.data.path); },
  });
  const deleteFolder = useMutation({
    mutationFn: (path: string) => assetFolders.delete(siteId, path),
    onSuccess: (_r, path) => { invalidate(); if (folder === path || folder.startsWith(path + '/')) setFolder(ALL); },
  });

  const handleFiles = useCallback((files: FileList | null) => {
    if (!files || files.length === 0) return;
    const list = Array.from(files);
    uploadMutation.mutate(multiple ? list : [list[0]]);
  }, [uploadMutation, multiple]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  }, [handleFiles]);

  const filtered = useMemo(() => (assetList || []).filter(a => {
    if (search) {
      const q = search.toLowerCase();
      if (!a.original_name?.toLowerCase().includes(q) && !a.filename?.toLowerCase().includes(q)) return false;
    }
    return true;
  }), [assetList, search]);

  const excluded = useMemo(() => new Set(excludeUrls || []), [excludeUrls]);

  const toggle = (a: Asset) => {
    const picked = toPicked(a);
    if (!multiple) { onSelect(picked); return; }
    setSelected(prev => {
      const next = new Map(prev);
      if (next.has(a.id)) next.delete(a.id); else next.set(a.id, picked);
      return next;
    });
  };

  const selectAllVisible = () => {
    setSelected(prev => {
      const next = new Map(prev);
      filtered.forEach(a => { if (!excluded.has(a.url)) next.set(a.id, toPicked(a)); });
      return next;
    });
  };

  if (!open) return null;

  const acceptMime = accept === 'image' ? 'image/*' : accept === 'video' ? 'video/*' : accept === 'audio' ? 'audio/*' : '*/*';
  const title = accept === 'image' ? (multiple ? 'Choose images' : 'Choose image') : accept === 'video' ? 'Choose video' : accept === 'audio' ? 'Choose audio' : (multiple ? 'Choose files' : 'Choose file');
  const crumbs = folder === ALL ? [] : folder === '' ? [] : folder.split('/');

  return (
    <dialog className="modal modal-open" onClick={onClose}>
      <div className="modal-box bg-base-100 max-w-5xl w-[96vw] h-[86vh] max-h-[86vh] flex flex-col p-0 overflow-hidden" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-base-300/20 shrink-0">
          <h3 className="text-sm font-medium text-base-content/80">{title}</h3>
          <div className="flex items-center gap-2">
            <label className="input input-bordered input-sm flex items-center gap-2 text-[12px] w-56">
              <Search className="h-3.5 w-3.5 text-base-content/30" />
              <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search files..." className="grow bg-transparent" />
            </label>
            <button type="button" onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
          </div>
        </div>

        <div className="flex flex-1 min-h-0">
          {/* Folder tree */}
          <aside className="w-52 shrink-0 border-r border-base-300/20 flex flex-col">
            <div className="flex-1 overflow-y-auto py-2">
              <FolderRow active={folder === ALL} depth={0} icon={FolderOpen} label="All files" onClick={() => setFolder(ALL)} />
              <FolderRow active={folder === ''} depth={0} icon={Folder} label="Root" count={folderData?.root_count} onClick={() => setFolder('')} />
              {folders.map(f => (
                <FolderRow key={f.path} active={folder === f.path} depth={f.path.split('/').length} icon={folder === f.path ? FolderOpen : Folder}
                  label={f.name} count={f.count} onClick={() => setFolder(f.path)}
                  onDelete={() => { if (confirm(`Delete folder "${f.path}"?\nFiles inside are kept and moved to the parent folder.`)) deleteFolder.mutate(f.path); }} />
              ))}
            </div>
            <div className="p-2 border-t border-base-300/20">
              {newFolderOpen ? (
                <form className="flex flex-col gap-1" onSubmit={e => { e.preventDefault(); if (newFolderName.trim()) createFolder.mutate(newFolderName.trim()); }}>
                  <input autoFocus value={newFolderName} onChange={e => setNewFolderName(e.target.value)} placeholder="Folder name"
                    className="input input-bordered input-xs w-full text-[11px]" />
                  <div className="flex gap-1">
                    <button type="submit" disabled={createFolder.isPending || !newFolderName.trim()} className="btn btn-primary btn-xs flex-1 text-[11px]">Create</button>
                    <button type="button" onClick={() => { setNewFolderOpen(false); setNewFolderName(''); }} className="btn btn-ghost btn-xs text-[11px]">Cancel</button>
                  </div>
                  {createFolder.isError && <p className="text-[10px] text-error">{(createFolder.error as any)?.response?.data?.message || 'Could not create folder'}</p>}
                  {folder !== ALL && folder !== '' && <p className="text-[10px] text-base-content/40">inside “{folder}”</p>}
                </form>
              ) : (
                <button type="button" onClick={() => setNewFolderOpen(true)} className="btn btn-ghost btn-xs w-full text-[11px] gap-1 justify-start">
                  <FolderPlus size={12} /> New folder
                </button>
              )}
            </div>
          </aside>

          {/* Files */}
          <div className="flex-1 min-w-0 flex flex-col">
            {/* Breadcrumb + upload */}
            <div className="flex items-center gap-2 px-4 py-2 border-b border-base-300/20 text-[11px] shrink-0">
              <span className="flex items-center gap-1 text-base-content/50 min-w-0 truncate">
                <button type="button" className="hover:text-base-content" onClick={() => setFolder(folder === ALL ? ALL : '')}>{folder === ALL ? 'All files' : 'Root'}</button>
                {crumbs.map((c, i) => (
                  <span key={i} className="flex items-center gap-1">
                    <ChevronRight size={10} />
                    <button type="button" className="hover:text-base-content" onClick={() => setFolder(crumbs.slice(0, i + 1).join('/'))}>{c}</button>
                  </span>
                ))}
              </span>
              <div className="flex-1" />
              {multiple && filtered.length > 0 && (
                <button type="button" onClick={selectAllVisible} className="btn btn-ghost btn-xs text-[11px]">Select all shown</button>
              )}
              <button type="button" onClick={() => fileInputRef.current?.click()} disabled={uploadMutation.isPending}
                className="btn btn-primary btn-xs text-[11px] gap-1">
                {uploadMutation.isPending ? <Loader2 size={11} className="animate-spin" /> : <Upload size={11} />}
                {multiple ? 'Upload files' : 'Upload'}{uploadFolder ? ` → ${uploadFolder}` : ''}
              </button>
              <input ref={fileInputRef} type="file" accept={acceptMime} multiple={multiple} className="hidden"
                onChange={e => { handleFiles(e.target.files); e.target.value = ''; }} />
            </div>

            {uploadProgress && (
              <div className="px-4 py-1.5 text-[11px] border-b border-base-300/20 bg-base-200/40 shrink-0">
                {uploadProgress.done < uploadProgress.total
                  ? <span className="flex items-center gap-2"><Loader2 size={11} className="animate-spin text-primary" /> Uploading {uploadProgress.done + 1} of {uploadProgress.total}…</span>
                  : <span className="text-base-content/60">Uploaded {uploadProgress.total - uploadProgress.errors.length} of {uploadProgress.total}</span>}
                {uploadProgress.errors.map((e, i) => <p key={i} className="text-error mt-0.5">{e}</p>)}
                {uploadProgress.done >= uploadProgress.total && (
                  <button type="button" className="ml-2 link text-[10px]" onClick={() => setUploadProgress(null)}>dismiss</button>
                )}
              </div>
            )}

            {/* Grid / drop zone */}
            <div
              className={`flex-1 overflow-y-auto p-4 transition-colors ${isDragging ? 'bg-primary/5 ring-2 ring-inset ring-primary/40' : ''}`}
              onDragOver={e => { e.preventDefault(); setIsDragging(true); }}
              onDragLeave={() => setIsDragging(false)}
              onDrop={handleDrop}
            >
              {isLoading && (
                <div className="flex justify-center py-10"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>
              )}

              {!isLoading && filtered.length === 0 && (
                <div className="text-center py-14 text-[12px] text-base-content/30">
                  <Upload size={22} className="mx-auto mb-2 text-base-content/15" />
                  {search ? 'No files match your search' : 'No files here yet. Drag & drop files anywhere in this area, or use Upload.'}
                </div>
              )}

              <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2">
                {filtered.map(asset => {
                  const isImg = asset.mime_type?.startsWith('image/');
                  const isSel = multiple ? selected.has(asset.id) : currentUrl === asset.url;
                  const inUse = multiple && excluded.has(asset.url);
                  const Icon = getIcon(asset.mime_type);

                  return (
                    <button key={asset.id} type="button"
                      onClick={() => toggle(asset)}
                      title={inUse ? 'Already in this gallery' : asset.original_name}
                      className={`relative rounded-lg border-2 overflow-hidden text-left transition-all hover:border-primary/50 ${
                        isSel ? 'border-primary ring-2 ring-primary/20' : 'border-base-300/30'
                      } ${inUse ? 'opacity-50' : ''}`}>
                      <div className="aspect-square bg-base-200/50 flex items-center justify-center overflow-hidden">
                        {isImg ? (
                          <img src={asset.url} alt={asset.original_name} className="w-full h-full object-cover" loading="lazy" />
                        ) : (
                          <Icon size={24} className="text-base-content/15" />
                        )}
                      </div>
                      <div className="p-1.5">
                        <p className="text-[10px] text-base-content/60 truncate">{asset.original_name || asset.filename}</p>
                        <p className="text-[9px] text-base-content/30 truncate">
                          {formatSize(asset.size ?? asset.file_size ?? 0)}{folder === ALL && asset.folder ? ` · ${asset.folder}` : ''}
                        </p>
                      </div>
                      {(isSel || inUse) && (
                        <div className={`absolute top-1.5 right-1.5 w-5 h-5 rounded-full flex items-center justify-center ${isSel ? 'bg-primary' : 'bg-base-content/40'}`}>
                          <Check size={10} className="text-primary-content" />
                        </div>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between px-4 py-3 border-t border-base-300/20 shrink-0">
          <span className="text-[11px] text-base-content/30">
            {filtered.length} files{multiple && selected.size > 0 ? ` · ${selected.size} selected` : ''}
          </span>
          <div className="flex items-center gap-2">
            {multiple && selected.size > 0 && (
              <button type="button" onClick={() => setSelected(new Map())} className="btn btn-ghost btn-sm text-[12px]">Clear selection</button>
            )}
            <button type="button" onClick={onClose} className="btn btn-ghost btn-sm text-[12px]">{multiple ? 'Cancel' : 'Close'}</button>
            {multiple && (
              <button type="button" disabled={selected.size === 0}
                onClick={() => { onSelectMany?.(Array.from(selected.values())); }}
                className="btn btn-primary btn-sm text-[12px]">
                Add {selected.size > 0 ? selected.size : ''} {selected.size === 1 ? 'file' : 'files'}
              </button>
            )}
          </div>
        </div>
      </div>
      <form method="dialog" className="modal-backdrop"><button type="button" onClick={onClose}>close</button></form>
    </dialog>
  );
}

function FolderRow({ active, depth, icon: Icon, label, count, onClick, onDelete }: {
  active: boolean; depth: number; icon: any; label: string; count?: number; onClick: () => void; onDelete?: () => void;
}) {
  return (
    <div className={`group flex items-center gap-1.5 pr-2 py-1 text-[11px] cursor-pointer ${active ? 'bg-primary/10 text-base-content' : 'text-base-content/60 hover:bg-base-200/60'}`}
      style={{ paddingLeft: 10 + depth * 12 }} onClick={onClick}>
      <Icon size={12} className={active ? 'text-primary' : 'text-base-content/40'} />
      <span className="truncate flex-1">{label}</span>
      {typeof count === 'number' && <span className="text-[9px] text-base-content/30">{count}</span>}
      {onDelete && (
        <button type="button" title="Delete folder" onClick={e => { e.stopPropagation(); onDelete(); }}
          className="opacity-0 group-hover:opacity-100 text-base-content/30 hover:text-error"><Trash2 size={10} /></button>
      )}
    </div>
  );
}

/**
 * Inline asset field — replaces raw URL inputs.
 * Shows a thumbnail + pick/change button.
 */
export function AssetField({ label, value, onChange, accept = 'image', autoOpen, onAutoOpenDone }: {
  label: string;
  value: string;
  onChange: (url: string, assetId?: string) => void;
  accept?: 'image' | 'video' | 'audio' | 'all';
  autoOpen?: boolean;
  onAutoOpenDone?: () => void;
}) {
  const [pickerOpen, setPickerOpen] = useState(false);

  // Auto-open picker when requested (e.g., after creating a new image element)
  useEffect(() => {
    if (autoOpen && !pickerOpen) {
      setPickerOpen(true);
      onAutoOpenDone?.();
    }
  }, [autoOpen]);
  const isImage = accept === 'image' && value;

  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1 block">{label}</label>

      {/* Preview */}
      {isImage && value && (
        <div className="mb-1.5 rounded overflow-hidden border border-base-300/20 relative group">
          <img src={value} alt="" className="w-full h-24 object-cover" onError={e => (e.target as HTMLImageElement).style.display = 'none'} />
          <button onClick={() => onChange('')}
            className="absolute top-1 right-1 btn btn-xs btn-circle bg-base-100/80 opacity-0 group-hover:opacity-100 transition-opacity">
            <X size={10} />
          </button>
        </div>
      )}

      {/* Audio/video preview */}
      {accept === 'audio' && value && (
        <div className="mb-1.5 p-2 bg-base-200/50 rounded border border-base-300/20">
          <audio src={value} controls className="w-full h-8" />
        </div>
      )}
      {accept === 'video' && value && !value.includes('youtube') && !value.includes('vimeo') && (
        <div className="mb-1.5 rounded overflow-hidden border border-base-300/20">
          <video src={value} className="w-full h-24 object-cover" />
        </div>
      )}

      <div className="flex gap-1.5">
        <button onClick={() => setPickerOpen(true)}
          className="btn btn-ghost btn-xs text-[11px] gap-1 flex-1">
          <Upload size={10} />
          {value ? 'Change' : 'Upload / choose'}
        </button>
        {value && (
          <button onClick={() => onChange('')} className="btn btn-ghost btn-xs text-[11px] text-error">Clear</button>
        )}
      </div>

      {/* Hidden URL input for manual entry */}
      <input value={value} onChange={e => onChange(e.target.value)}
        className="input input-bordered input-xs w-full text-[10px] mt-1 font-mono" placeholder="Or paste URL..." />

      <AssetPicker
        open={pickerOpen}
        onClose={() => setPickerOpen(false)}
        onSelect={(asset) => { onChange(asset.url, asset.id); setPickerOpen(false); }}
        accept={accept}
        currentUrl={value}
      />
    </div>
  );
}
