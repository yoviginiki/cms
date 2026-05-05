import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Palette, Plus, Copy, Loader2, Check, Download, Upload, Eye } from 'lucide-react';
import { themeEngine } from '@/lib/api';

interface ThemeItem {
  id: string;
  name: string;
  slug: string;
  description?: string;
  modes?: string[];
  is_system: boolean;
  is_assigned: boolean;
  parent_theme_id?: string;
}

export default function ThemeEngine() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [importJson, setImportJson] = useState('');
  const [showImport, setShowImport] = useState(false);

  const { data: themes, isLoading, error } = useQuery<ThemeItem[]>({
    queryKey: ['theme-engine', siteId],
    queryFn: () => themeEngine.list(siteId).then((r: any) => {
      const d = r.data?.data;
      return Array.isArray(d) ? d : [];
    }),
  });

  const forkMut = useMutation({
    mutationFn: ({ themeId, name }: { themeId: string; name: string }) =>
      themeEngine.fork(siteId, themeId, name),
    onSuccess: (r: any) => {
      queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] });
      navigate(`/sites/${siteId}/theme-engine/${r.data.data.id}`);
    },
  });

  const assignMut = useMutation({
    mutationFn: (themeId: string) => themeEngine.assign(siteId, themeId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] }),
  });

  const importMut = useMutation({
    mutationFn: (doc: Record<string, unknown>) => themeEngine.importTheme(siteId, { document: doc }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] });
      setShowImport(false);
      setImportJson('');
    },
  });

  const handleFork = (theme: ThemeItem) => {
    const name = prompt('Name for the forked theme:', theme.name + ' (Custom)');
    if (name) forkMut.mutate({ themeId: theme.id, name });
  };

  const handleImport = () => {
    try {
      const doc = JSON.parse(importJson);
      importMut.mutate(doc);
    } catch {
      alert('Invalid JSON');
    }
  };

  const systemThemes = themes?.filter(t => t.is_system) || [];
  const customThemes = themes?.filter(t => !t.is_system) || [];

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <Palette className="h-6 w-6 text-purple-500" /> Theme Engine
          </h1>
          <p className="mt-1 text-sm text-gray-500">W3C Design Tokens-based theme system</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => setShowImport(true)}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
            <Upload className="h-3.5 w-3.5" /> Import JSON
          </button>
        </div>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-sm text-red-700">
          Failed to load themes: {(error as any)?.message || 'Unknown error'}
        </div>
      )}

      {/* System Themes */}
      {systemThemes.length > 0 && (
        <div className="mb-8">
          <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">System Themes</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {systemThemes.map(theme => (
              <ThemeCard key={theme.id} theme={theme} siteId={siteId}
                onFork={() => handleFork(theme)}
                onAssign={() => assignMut.mutate(theme.id)}
                onEdit={() => navigate(`/sites/${siteId}/theme-engine/${theme.id}`)} />
            ))}
          </div>
        </div>
      )}

      {/* Custom Themes */}
      <div>
        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
          Custom Themes {customThemes.length > 0 && `(${customThemes.length})`}
        </h2>
        {customThemes.length === 0 ? (
          <div className="text-center py-12 bg-white rounded-xl border border-gray-200">
            <Palette className="h-10 w-10 mx-auto mb-3 text-gray-200" />
            <p className="text-sm text-gray-400 mb-1">No custom themes yet</p>
            <p className="text-xs text-gray-300">Fork a system theme to start customizing</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {customThemes.map(theme => (
              <ThemeCard key={theme.id} theme={theme} siteId={siteId}
                onFork={() => handleFork(theme)}
                onAssign={() => assignMut.mutate(theme.id)}
                onEdit={() => navigate(`/sites/${siteId}/theme-engine/${theme.id}`)} />
            ))}
          </div>
        )}
      </div>

      {/* Import dialog */}
      {showImport && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowImport(false)}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-semibold mb-4">Import W3C Tokens JSON</h3>
            <textarea value={importJson} onChange={e => setImportJson(e.target.value)}
              className="w-full h-64 border border-gray-200 rounded-lg p-3 font-mono text-xs"
              placeholder='Paste your W3C Design Tokens JSON here...' />
            <div className="flex justify-end gap-2 mt-4">
              <button onClick={() => setShowImport(false)} className="px-4 py-2 text-sm text-gray-700 border rounded-lg">Cancel</button>
              <button onClick={handleImport} disabled={importMut.isPending || !importJson.trim()}
                className="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50">
                {importMut.isPending ? 'Importing...' : 'Import'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function ThemeCard({ theme, siteId, onFork, onAssign, onEdit }: {
  theme: ThemeItem; siteId: string;
  onFork: () => void; onAssign: () => void; onEdit: () => void;
}) {
  return (
    <div className={`bg-white rounded-xl border p-4 hover:shadow-md transition-shadow ${
      theme.is_assigned ? 'border-purple-300 ring-1 ring-purple-200' : 'border-gray-200'
    }`}>
      <div className="flex items-start justify-between mb-2">
        <div>
          <h3 className="font-semibold text-gray-900">{theme.name}</h3>
          {theme.description && (
            <p className="text-xs text-gray-400 mt-0.5 line-clamp-2">{theme.description}</p>
          )}
        </div>
        {theme.is_assigned && (
          <span className="bg-purple-100 text-purple-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Active</span>
        )}
      </div>

      <div className="flex items-center gap-1.5 mb-3">
        {theme.modes?.map(m => (
          <span key={m} className="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">{m}</span>
        ))}
        {theme.is_system && (
          <span className="text-[10px] bg-blue-50 text-blue-500 px-1.5 py-0.5 rounded">System</span>
        )}
      </div>

      <div className="flex items-center gap-1.5">
        <button onClick={onEdit}
          className="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
          <Eye className="h-3 w-3" /> {theme.is_system ? 'View' : 'Edit'}
        </button>
        {theme.is_system && (
          <button onClick={onFork}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            <Copy className="h-3 w-3" /> Fork
          </button>
        )}
        {!theme.is_assigned && (
          <button onClick={onAssign}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-purple-600 text-white hover:bg-purple-700">
            <Check className="h-3 w-3" /> Assign
          </button>
        )}
      </div>
    </div>
  );
}
