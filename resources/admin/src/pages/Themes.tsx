import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Upload, Check, Trash2, Loader2, Palette, Download, Star } from 'lucide-react';
import { api } from '@/lib/api';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface ThemeData {
  id: string;
  name: string;
  slug: string;
  version: string;
  description: string;
  screenshot: string | null;
  is_active: boolean;
  is_system: boolean;
  tokens_count: number;
}

export default function Themes() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [deleteTarget, setDeleteTarget] = useState<ThemeData | null>(null);

  const { data: themes, isLoading } = useQuery<ThemeData[]>({
    queryKey: ['themes', siteId],
    queryFn: () => api.get(`/sites/${siteId}/themes`).then(r => r.data.data),
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData();
      fd.append('file', file);
      return api.post(`/sites/${siteId}/themes/upload`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['themes', siteId] }),
  });

  const activateMutation = useMutation({
    mutationFn: (themeId: string) => api.post(`/sites/${siteId}/themes/${themeId}/activate`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['themes', siteId] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (themeId: string) => api.delete(`/sites/${siteId}/themes/${themeId}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['themes', siteId] });
      setDeleteTarget(null);
    },
  });

  const handleUpload = (files: FileList | null) => {
    if (!files || files.length === 0) return;
    uploadMutation.mutate(files[0]);
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Themes</h1>
          <p className="mt-1 text-sm text-gray-500">Manage your site's appearance</p>
        </div>
        <button
          onClick={() => fileInputRef.current?.click()}
          disabled={uploadMutation.isPending}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {uploadMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
          Upload Theme
        </button>
        <input ref={fileInputRef} type="file" accept=".zip" className="hidden" onChange={e => handleUpload(e.target.files)} />
      </div>

      {uploadMutation.isSuccess && (
        <div className="mb-6 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
          Theme installed successfully! Click "Activate" to use it, then publish your site.
        </div>
      )}
      {uploadMutation.isError && (
        <div className="mb-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
          {(uploadMutation.error as any)?.response?.data?.message || 'Failed to install theme'}
        </div>
      )}

      {/* Upload drop zone */}
      <div
        onDrop={e => { e.preventDefault(); handleUpload(e.dataTransfer.files); }}
        onDragOver={e => e.preventDefault()}
        className="mb-8 rounded-xl border-2 border-dashed border-gray-300 p-6 text-center hover:border-gray-400 transition-colors"
      >
        <Upload className="h-8 w-8 mx-auto mb-2 text-gray-400" />
        <p className="text-sm text-gray-500">
          Drag and drop a theme ZIP file, or <button onClick={() => fileInputRef.current?.click()} className="text-blue-600 font-medium">browse</button>
        </p>
        <p className="text-xs text-gray-400 mt-1">ZIP must contain a theme.json manifest with name, tokens, and style.css</p>
      </div>

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>}

      {themes && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {themes.map(theme => (
            <div key={theme.id} className={`bg-white rounded-xl border-2 shadow-sm overflow-hidden transition-all ${
              theme.is_active ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:shadow-md'
            }`}>
              {/* Screenshot / Preview */}
              <div className="aspect-[16/10] bg-gradient-to-br from-gray-100 to-gray-50 flex items-center justify-center relative">
                {theme.screenshot ? (
                  <img src={theme.screenshot} alt={theme.name} className="w-full h-full object-cover" />
                ) : (
                  <Palette className="h-12 w-12 text-gray-300" />
                )}
                {theme.is_active && (
                  <div className="absolute top-3 right-3 flex items-center gap-1 px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded-full">
                    <Check className="h-3 w-3" /> Active
                  </div>
                )}
                {theme.is_system && (
                  <div className="absolute top-3 left-3 flex items-center gap-1 px-2 py-1 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">
                    <Star className="h-3 w-3" /> Built-in
                  </div>
                )}
              </div>

              {/* Info */}
              <div className="p-4">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <h3 className="font-semibold text-gray-900">{theme.name}</h3>
                    <p className="text-xs text-gray-400">v{theme.version}</p>
                  </div>
                </div>
                {theme.description && <p className="text-sm text-gray-500 mb-3">{theme.description}</p>}
                <p className="text-xs text-gray-400 mb-4">{theme.tokens_count} design tokens</p>

                {/* Actions */}
                <div className="flex gap-2">
                  {!theme.is_active && (
                    <button
                      onClick={() => activateMutation.mutate(theme.id)}
                      disabled={activateMutation.isPending}
                      className="flex-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                      Activate
                    </button>
                  )}
                  {theme.is_active && (
                    <span className="flex-1 px-3 py-1.5 bg-green-50 text-green-700 text-sm font-medium rounded-lg text-center">
                      Active Theme
                    </span>
                  )}
                  <a href={`/api/v1/sites/${siteId}/themes/${theme.id}/export`}
                    className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" title="Export">
                    <Download className="h-4 w-4" />
                  </a>
                  {!theme.is_active && !theme.is_system && (
                    <button onClick={() => setDeleteTarget(theme)}
                      className="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-gray-100" title="Delete">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete theme"
        message={`Delete "${deleteTarget?.name}"? This cannot be undone.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
