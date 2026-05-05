import { useState, useRef, useCallback, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Upload, X, Search, Image, Film, Music, File, Check, Loader2 } from 'lucide-react';
import { assets } from '@/lib/api';

interface Asset {
  id: string;
  filename: string;
  original_name: string;
  mime_type: string;
  size: number;
  url: string;
  created_at: string;
}

interface AssetPickerProps {
  open: boolean;
  onClose: () => void;
  onSelect: (asset: { id: string; url: string; filename: string; mime_type: string }) => void;
  accept?: 'image' | 'video' | 'audio' | 'all';
  currentUrl?: string;
}

function formatSize(bytes: number): string {
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

export function AssetPicker({ open, onClose, onSelect, accept = 'all', currentUrl }: AssetPickerProps) {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [search, setSearch] = useState('');
  const [isDragging, setIsDragging] = useState(false);

  const typeFilter = accept === 'all' ? undefined : accept;

  const { data: assetList, isLoading } = useQuery<Asset[]>({
    queryKey: ['assets-picker', siteId, typeFilter],
    queryFn: () => {
      const params: Record<string, unknown> = {};
      if (typeFilter) params.type = typeFilter;
      return assets.list(siteId, params).then((r: any) => r.data.data);
    },
    enabled: open,
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => assets.upload(siteId, file),
    onSuccess: (r: any) => {
      queryClient.invalidateQueries({ queryKey: ['assets-picker', siteId] });
      queryClient.invalidateQueries({ queryKey: ['assets', siteId] });
      const asset = r.data.data;
      onSelect({ id: asset.id, url: asset.url, filename: asset.original_name || asset.filename, mime_type: asset.mime_type });
    },
  });

  const handleFiles = useCallback((files: FileList | null) => {
    if (!files || files.length === 0) return;
    uploadMutation.mutate(files[0]);
  }, [uploadMutation]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  }, [handleFiles]);

  const filtered = (assetList || []).filter(a => {
    if (search) {
      const q = search.toLowerCase();
      if (!a.original_name?.toLowerCase().includes(q) && !a.filename?.toLowerCase().includes(q)) return false;
    }
    return true;
  });

  if (!open) return null;

  const acceptMime = accept === 'image' ? 'image/*' : accept === 'video' ? 'video/*' : accept === 'audio' ? 'audio/*' : '*/*';

  return (
    <dialog className="modal modal-open" onClick={onClose}>
      <div className="modal-box bg-base-100 max-w-3xl max-h-[80vh] flex flex-col" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center justify-between mb-3 shrink-0">
          <h3 className="text-sm font-medium text-base-content/80">
            {accept === 'image' ? 'Choose image' : accept === 'video' ? 'Choose video' : accept === 'audio' ? 'Choose audio' : 'Choose file'}
          </h3>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
        </div>

        {/* Upload zone */}
        <div
          className={`border-2 border-dashed rounded-lg p-4 mb-3 text-center transition-colors shrink-0 ${
            isDragging ? 'border-primary bg-primary/5' : 'border-base-300/40 hover:border-base-300/70'
          }`}
          onDragOver={e => { e.preventDefault(); setIsDragging(true); }}
          onDragLeave={() => setIsDragging(false)}
          onDrop={handleDrop}
        >
          {uploadMutation.isPending ? (
            <div className="flex items-center justify-center gap-2 py-2">
              <Loader2 size={16} className="animate-spin text-primary" />
              <span className="text-[12px] text-base-content/50">Uploading...</span>
            </div>
          ) : (
            <>
              <Upload size={20} className="mx-auto mb-1 text-base-content/20" />
              <p className="text-[12px] text-base-content/40">
                Drag & drop or{' '}
                <button onClick={() => fileInputRef.current?.click()} className="text-primary hover:underline">browse files</button>
              </p>
              <input ref={fileInputRef} type="file" accept={acceptMime} className="hidden"
                onChange={e => handleFiles(e.target.files)} />
            </>
          )}
          {uploadMutation.isError && (
            <p className="text-[11px] text-error mt-1">{(uploadMutation.error as any)?.response?.data?.message || 'Upload failed'}</p>
          )}
        </div>

        {/* Search */}
        <label className="input input-bordered input-sm flex items-center gap-2 text-[12px] mb-3 shrink-0">
          <Search className="h-3.5 w-3.5 text-base-content/30" />
          <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search files..." className="grow bg-transparent" />
        </label>

        {/* Asset grid */}
        <div className="flex-1 overflow-y-auto">
          {isLoading && (
            <div className="flex justify-center py-10"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>
          )}

          {!isLoading && filtered.length === 0 && (
            <div className="text-center py-10 text-[12px] text-base-content/25">
              {search ? 'No files match your search' : 'No files uploaded yet. Drag & drop above to upload.'}
            </div>
          )}

          <div className="grid grid-cols-4 gap-2">
            {filtered.map(asset => {
              const isImg = asset.mime_type?.startsWith('image/');
              const isSelected = currentUrl === asset.url;
              const Icon = getIcon(asset.mime_type);

              return (
                <button key={asset.id}
                  onClick={() => onSelect({ id: asset.id, url: asset.url, filename: asset.original_name || asset.filename, mime_type: asset.mime_type })}
                  className={`relative rounded-lg border-2 overflow-hidden text-left transition-all hover:border-primary/50 ${
                    isSelected ? 'border-primary ring-2 ring-primary/20' : 'border-base-300/30'
                  }`}>
                  {/* Preview */}
                  <div className="aspect-square bg-base-200/50 flex items-center justify-center overflow-hidden">
                    {isImg ? (
                      <img src={asset.url} alt={asset.original_name} className="w-full h-full object-cover" loading="lazy" />
                    ) : (
                      <Icon size={24} className="text-base-content/15" />
                    )}
                  </div>

                  {/* Info */}
                  <div className="p-1.5">
                    <p className="text-[10px] text-base-content/60 truncate">{asset.original_name || asset.filename}</p>
                    <p className="text-[9px] text-base-content/30">{formatSize(asset.size)}</p>
                  </div>

                  {/* Selected check */}
                  {isSelected && (
                    <div className="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-primary flex items-center justify-center">
                      <Check size={10} className="text-primary-content" />
                    </div>
                  )}
                </button>
              );
            })}
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between pt-3 mt-3 border-t border-base-300/20 shrink-0">
          <span className="text-[11px] text-base-content/30">{filtered.length} files</span>
          <button onClick={onClose} className="btn btn-ghost btn-sm text-[12px]">Close</button>
        </div>
      </div>
      <form method="dialog" className="modal-backdrop"><button onClick={onClose}>close</button></form>
    </dialog>
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
