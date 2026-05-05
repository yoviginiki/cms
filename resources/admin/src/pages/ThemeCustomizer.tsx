import { useState, useCallback, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Save, RotateCcw, Download, Loader2, Palette, Type, Maximize2, Sparkles } from 'lucide-react';
import { api } from '@/lib/api';

interface TokenData {
  key: string;
  default: string;
  theme: string | null;
  custom: string | null;
  value: string;
  type: 'color' | 'font' | 'dimension' | 'shadow' | 'transition' | 'text';
}

const tokenGroups = [
  { id: 'colors', label: 'Colors', icon: Palette, prefix: 'color-' },
  { id: 'typography', label: 'Typography', icon: Type, prefix: 'font-' },
  { id: 'spacing', label: 'Spacing & Layout', icon: Maximize2, prefix: 'space-' },
  { id: 'other', label: 'Effects', icon: Sparkles, prefix: '' },
];

export default function ThemeCustomizer() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const [activeGroup, setActiveGroup] = useState('colors');
  const [changes, setChanges] = useState<Record<string, string>>({});
  const previewRef = useRef<HTMLIFrameElement>(null);

  const { data: tokens, isLoading } = useQuery<Record<string, TokenData>>({
    queryKey: ['theme-tokens', siteId],
    queryFn: () => api.get(`/sites/${siteId}/theme/tokens`).then(r => r.data.data),
  });

  const saveMutation = useMutation({
    mutationFn: (tokenList: { key: string; value: string }[]) =>
      api.post(`/sites/${siteId}/theme/tokens`, { tokens: tokenList }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['theme-tokens', siteId] });
      setChanges({});
    },
  });

  const resetMutation = useMutation({
    mutationFn: () => api.delete(`/sites/${siteId}/theme/tokens`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['theme-tokens', siteId] });
      setChanges({});
    },
  });

  const handleChange = useCallback((key: string, value: string) => {
    setChanges(prev => ({ ...prev, [key]: value }));
  }, []);

  const handleSave = () => {
    const tokenList = Object.entries(changes).map(([key, value]) => ({ key, value }));
    if (tokenList.length > 0) saveMutation.mutate(tokenList);
  };

  const handleExport = async () => {
    const res = await api.get(`/sites/${siteId}/theme/export`);
    const blob = new Blob([JSON.stringify(res.data.data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'theme-preset.json'; a.click();
    URL.revokeObjectURL(url);
  };

  const getValue = (key: string) => changes[key] ?? tokens?.[key]?.value ?? '';

  const filteredTokens = tokens ? Object.values(tokens).filter(t => {
    if (activeGroup === 'colors') return t.key.startsWith('color-');
    if (activeGroup === 'typography') return t.key.startsWith('font-') || t.key.startsWith('line-') || t.key.startsWith('letter-');
    if (activeGroup === 'spacing') return t.key.startsWith('space-') || t.key.startsWith('container-') || t.key.startsWith('grid-') || t.key.startsWith('border-radius');
    return t.key.startsWith('shadow-') || t.key.startsWith('transition-');
  }) : [];

  const isDirty = Object.keys(changes).length > 0;

  if (isLoading) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  return (
    <div className="flex h-[calc(100vh-60px)]">
      {/* Left: Token Controls */}
      <div className="w-96 bg-white border-r border-gray-200 flex flex-col shrink-0">
        {/* Header */}
        <div className="p-4 border-b border-gray-200">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-lg font-bold text-gray-900">Theme Customizer</h2>
            <div className="flex gap-1">
              <button onClick={handleExport} className="p-1.5 text-gray-400 hover:text-gray-600 rounded" title="Export"><Download size={16} /></button>
              <button onClick={() => resetMutation.mutate()} className="p-1.5 text-gray-400 hover:text-red-500 rounded" title="Reset"><RotateCcw size={16} /></button>
            </div>
          </div>
          <button onClick={handleSave} disabled={!isDirty || saveMutation.isPending}
            className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {saveMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
            {isDirty ? `Save ${Object.keys(changes).length} changes` : 'No changes'}
          </button>
        </div>

        {/* Group tabs */}
        <div className="flex border-b border-gray-200">
          {tokenGroups.map(g => (
            <button key={g.id} onClick={() => setActiveGroup(g.id)}
              className={`flex-1 px-2 py-2.5 text-xs font-medium text-center border-b-2 transition-colors ${
                activeGroup === g.id ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}>
              <g.icon size={14} className="mx-auto mb-0.5" />
              {g.label}
            </button>
          ))}
        </div>

        {/* Token controls */}
        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {filteredTokens.map(token => (
            <div key={token.key}>
              <label className="block text-xs font-medium text-gray-600 mb-1">
                --{token.key}
                {changes[token.key] && <span className="ml-1 text-orange-500">*</span>}
              </label>
              {token.type === 'color' ? (
                <div className="flex items-center gap-2">
                  <input type="color" value={getValue(token.key)}
                    onChange={e => handleChange(token.key, e.target.value)}
                    className="h-8 w-10 rounded border border-gray-200 cursor-pointer" />
                  <input type="text" value={getValue(token.key)}
                    onChange={e => handleChange(token.key, e.target.value)}
                    className="flex-1 px-2 py-1.5 border border-gray-200 rounded text-xs font-mono" />
                </div>
              ) : token.type === 'font' ? (
                <input type="text" value={getValue(token.key)}
                  onChange={e => handleChange(token.key, e.target.value)}
                  placeholder="Font family..."
                  className="w-full px-2 py-1.5 border border-gray-200 rounded text-sm" />
              ) : (
                <input type="text" value={getValue(token.key)}
                  onChange={e => handleChange(token.key, e.target.value)}
                  className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs font-mono" />
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Right: Live Preview */}
      <div className="flex-1 bg-gray-100 p-4">
        <div className="bg-white rounded-xl shadow-lg overflow-hidden h-full">
          <div className="bg-gray-50 border-b border-gray-200 px-4 py-2 flex items-center justify-between">
            <span className="text-xs text-gray-500">Live Preview</span>
            <div className="flex gap-1">
              <div className="w-3 h-3 rounded-full bg-red-400"></div>
              <div className="w-3 h-3 rounded-full bg-yellow-400"></div>
              <div className="w-3 h-3 rounded-full bg-green-400"></div>
            </div>
          </div>
          <iframe
            ref={previewRef}
            src={`/api/v1/sites/${siteId}/pages/${''}/preview`}
            className="w-full border-0"
            style={{ height: 'calc(100% - 40px)' }}
            title="Theme preview"
          />
        </div>
      </div>
    </div>
  );
}
