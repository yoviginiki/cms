import { useState, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Upload, Trash2, Image, File, Loader2, X, Download, Search, Copy, Check } from 'lucide-react';
import { assets } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Asset {
  id: string;
  filename: string;
  original_name: string;
  mime_type: string;
  size: number;
  url: string;
  created_at: string;
}

type FilterType = 'all' | 'images' | 'documents' | 'video' | 'audio';

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function isImage(mime: string): boolean {
  return mime.startsWith('image/');
}

export default function Assets() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [deleteTarget, setDeleteTarget] = useState<Asset | null>(null);
  const [selectedAsset, setSelectedAsset] = useState<Asset | null>(null);
  const [filter, setFilter] = useState<FilterType>('all');
  const [isDragging, setIsDragging] = useState(false);
  const [search, setSearch] = useState('');
  const [copied, setCopied] = useState<string | null>(null);

  const { data, isLoading, error } = useQuery<Asset[]>({
    queryKey: ['assets', siteId, filter],
    queryFn: () => {
      const params: Record<string, unknown> = {};
      if (filter === 'images') params.type = 'image';
      if (filter === 'documents') params.type = 'document';
      if (filter === 'video') params.type = 'video';
      if (filter === 'audio') params.type = 'audio';
      return assets.list(siteId, params).then(r => r.data.data);
    },
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => assets.upload(siteId, file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['assets', siteId] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (assetId: string) => assets.delete(siteId, assetId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['assets', siteId] });
      setDeleteTarget(null);
      if (selectedAsset && deleteTarget && selectedAsset.id === deleteTarget.id) {
        setSelectedAsset(null);
      }
    },
  });

  const handleFiles = useCallback((files: FileList | null) => {
    if (!files) return;
    Array.from(files).forEach(file => uploadMutation.mutate(file));
  }, [uploadMutation]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  }, [handleFiles]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  }, []);

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">File Manager</h1>
          <p className="mt-1 text-sm text-gray-500">Upload and manage all site files</p>
        </div>
        <button
          onClick={() => fileInputRef.current?.click()}
          disabled={uploadMutation.isPending}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {uploadMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
          Upload
        </button>
        <input
          ref={fileInputRef}
          type="file"
          multiple
          className="hidden"
          onChange={(e) => handleFiles(e.target.files)}
        />
      </div>

      {/* Search */}
      <div className="mb-4 relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search files by name..."
          className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
      </div>

      {/* Upload drop zone */}
      <div
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        className={`mb-6 rounded-xl border-2 border-dashed p-8 text-center transition-colors ${
          isDragging
            ? 'border-blue-400 bg-blue-50'
            : 'border-gray-300 hover:border-gray-400'
        }`}
      >
        <Upload className={`h-8 w-8 mx-auto mb-2 ${isDragging ? 'text-blue-500' : 'text-gray-400'}`} />
        <p className="text-sm text-gray-600">
          Drag and drop files here, or{' '}
          <button
            onClick={() => fileInputRef.current?.click()}
            className="text-blue-600 hover:text-blue-800 font-medium"
          >
            browse
          </button>
        </p>
        {uploadMutation.isPending && (
          <div className="mt-3 flex items-center justify-center gap-2 text-sm text-blue-600">
            <Loader2 className="h-4 w-4 animate-spin" />
            Uploading...
          </div>
        )}
      </div>

      {/* Filter tabs */}
      <div className="flex items-center gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        {(['all', 'images', 'documents', 'video', 'audio'] as FilterType[]).map((f) => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            className={`px-4 py-1.5 text-sm font-medium rounded-md capitalize transition-colors ${
              filter === f
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            {f}
          </button>
        ))}
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          Failed to load assets. Please try again.
        </div>
      )}

      {data && data.length === 0 && (
        <EmptyState
          icon={Image}
          title="No assets yet"
          description="Upload your first file to get started"
          actionLabel="Upload File"
          onAction={() => fileInputRef.current?.click()}
        />
      )}

      {data && data.length > 0 && (() => {
        const filtered = search
          ? data.filter(a => a.original_name.toLowerCase().includes(search.toLowerCase()))
          : data;
        return (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {filtered.length === 0 && (
            <p className="col-span-full text-center text-sm text-gray-400 py-8">No files match "{search}"</p>
          )}
          {filtered.map((asset) => (
            <div
              key={asset.id}
              onClick={() => setSelectedAsset(asset)}
              className={`group bg-white rounded-xl border shadow-sm overflow-hidden cursor-pointer transition-all hover:shadow-md ${
                selectedAsset?.id === asset.id ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200'
              }`}
            >
              <div className="aspect-square bg-gray-50 flex items-center justify-center overflow-hidden">
                {isImage(asset.mime_type) ? (
                  <img src={asset.url} alt={asset.original_name} className="w-full h-full object-cover" />
                ) : (
                  <File className="h-12 w-12 text-gray-300" />
                )}
              </div>
              <div className="p-3">
                <p className="text-sm font-medium text-gray-900 truncate" title={asset.original_name}>
                  {asset.original_name}
                </p>
                <p className="text-xs text-gray-500 mt-0.5">{formatFileSize(asset.size)}</p>
              </div>
              <div className="px-3 pb-3 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                  onClick={(e) => { e.stopPropagation(); setDeleteTarget(asset); }}
                  className="w-full px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
                >
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
        );
      })()}

      {/* Asset detail panel */}
      {selectedAsset && (
        <div className="fixed inset-y-0 right-0 w-80 bg-white border-l border-gray-200 shadow-xl z-40 overflow-y-auto">
          <div className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Details</h3>
              <button
                onClick={() => setSelectedAsset(null)}
                className="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="mb-4 rounded-lg overflow-hidden bg-gray-50">
              {isImage(selectedAsset.mime_type) ? (
                <img src={selectedAsset.url} alt={selectedAsset.original_name} className="w-full" />
              ) : (
                <div className="flex items-center justify-center py-12">
                  <File className="h-16 w-16 text-gray-300" />
                </div>
              )}
            </div>

            <dl className="space-y-3 text-sm">
              <div>
                <dt className="text-gray-500">Filename</dt>
                <dd className="font-medium text-gray-900 break-all">{selectedAsset.original_name}</dd>
              </div>
              <div>
                <dt className="text-gray-500">Type</dt>
                <dd className="font-medium text-gray-900">{selectedAsset.mime_type}</dd>
              </div>
              <div>
                <dt className="text-gray-500">Size</dt>
                <dd className="font-medium text-gray-900">{formatFileSize(selectedAsset.size)}</dd>
              </div>
              <div>
                <dt className="text-gray-500">Uploaded</dt>
                <dd className="font-medium text-gray-900">{new Date(selectedAsset.created_at).toLocaleDateString()}</dd>
              </div>
              <div>
                <dt className="text-gray-500">URL</dt>
                <dd>
                  <input
                    type="text"
                    readOnly
                    value={selectedAsset.url}
                    onClick={(e) => (e.target as HTMLInputElement).select()}
                    className="w-full px-2 py-1 text-xs border border-gray-200 rounded bg-gray-50 text-gray-700"
                  />
                </dd>
              </div>
            </dl>

            <div className="mt-6 flex flex-col gap-2">
              <button
                onClick={() => {
                  navigator.clipboard.writeText(selectedAsset.url);
                  setCopied(selectedAsset.id);
                  setTimeout(() => setCopied(null), 2000);
                }}
                className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                {copied === selectedAsset.id ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
                {copied === selectedAsset.id ? 'Copied!' : 'Copy URL'}
              </button>
              <a
                href={selectedAsset.url}
                download
                className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                <Download className="h-4 w-4" />
                Download
              </a>
              <button
                onClick={() => setDeleteTarget(selectedAsset)}
                className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50"
              >
                <Trash2 className="h-4 w-4" />
                Delete
              </button>
            </div>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete asset"
        message={`Are you sure you want to delete "${deleteTarget?.original_name}"? This action cannot be undone.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
