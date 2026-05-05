import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Plus, Trash2, Loader2, ArrowUp, ArrowDown, Shield, Home, ArrowLeft, RefreshCw } from 'lucide-react';
import { api, publishing } from '@/lib/api';

interface Assignment {
  id: string;
  grid_id: string;
  assignable_type: string;
  assignable_id: string | null;
  priority: number;
  is_active: boolean;
  grid?: { id: string; name: string };
}

interface GridItem { id: string; name: string }
interface CategoryItem { id: string; name: string; slug: string }
interface PageItem { id: string; title: string; slug: string }

export default function GridAssignments() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const [showAdd, setShowAdd] = useState(false);
  const [newType, setNewType] = useState('category');
  const [newId, setNewId] = useState('');
  const [newGridId, setNewGridId] = useState('');
  const [error, setError] = useState('');
  const [rebuilding, setRebuilding] = useState(false);
  const [rebuildMsg, setRebuildMsg] = useState('');

  const inv = () => queryClient.invalidateQueries({ queryKey: ['grid-assignments', siteId] });

  const triggerRebuild = async () => {
    setRebuilding(true);
    setRebuildMsg('Rebuilding site...');
    try {
      await publishing.publish(siteId, 'full');
      setRebuildMsg('Site rebuilt!');
      setTimeout(() => setRebuildMsg(''), 3000);
    } catch {
      setRebuildMsg('Rebuild failed');
    } finally { setRebuilding(false); }
  };

  // Auto-rebuild when assignments change
  const invAndRebuild = () => { inv(); triggerRebuild(); };

  const { data: assignments, isLoading } = useQuery<Assignment[]>({
    queryKey: ['grid-assignments', siteId],
    queryFn: () => api.get(`/sites/${siteId}/grid-assignments`).then(r => r.data.data),
  });

  const { data: gridList } = useQuery<GridItem[]>({
    queryKey: ['grids-list', siteId],
    queryFn: () => api.get(`/sites/${siteId}/grids`).then(r => r.data.data),
  });

  const { data: catList } = useQuery<CategoryItem[]>({
    queryKey: ['cats-list', siteId],
    queryFn: () => api.get(`/sites/${siteId}/categories`).then(r => r.data.data),
  });

  const { data: pageList } = useQuery<PageItem[]>({
    queryKey: ['pages-list', siteId],
    queryFn: () => api.get(`/sites/${siteId}/pages`).then(r => r.data.data),
  });

  const { data: siteData } = useQuery<{ settings: Record<string, unknown> }>({
    queryKey: ['site', siteId],
    queryFn: () => api.get(`/sites/${siteId}`).then(r => r.data.data),
  });

  const homepageId = (siteData?.settings?.homepage_id as string) || '';
  const homepage = pageList?.find(p => p.id === homepageId) || pageList?.find(p => p.slug === 'home');
  const homepageAssignment = assignments?.find(a => a.assignable_type === 'page' && a.assignable_id === homepage?.id);
  const defaultAssignment = assignments?.find(a => a.assignable_type === 'default');

  const createMut = useMutation({
    mutationFn: async (data: Record<string, unknown>) => {
      const res = await api.post(`/sites/${siteId}/grid-assignments`, data);
      return res;
    },
    onSuccess: () => { invAndRebuild(); setShowAdd(false); setNewId(''); setNewGridId(''); setError(''); },
    onError: (err: any) => { setError(err.response?.data?.message || 'Failed to save'); },
  });

  const updateMut = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Record<string, unknown> }) =>
      api.put(`/sites/${siteId}/grid-assignments/${id}`, data),
    onSuccess: invAndRebuild,
  });

  const deleteMut = useMutation({
    mutationFn: (id: string) => api.delete(`/sites/${siteId}/grid-assignments/${id}`),
    onSuccess: invAndRebuild,
  });

  const getLabel = (a: Assignment) => {
    switch (a.assignable_type) {
      case 'default': return '🌐 Default — everything else';
      case 'post_type': return a.assignable_id === 'post' ? '📝 All blog posts' : '📄 All pages';
      case 'category': {
        const cat = catList?.find(c => c.id === a.assignable_id);
        return `📁 Category: ${cat?.name || '?'}`;
      }
      case 'page': {
        const pg = pageList?.find(p => p.id === a.assignable_id);
        return `📄 Page: ${pg?.title || '?'}`;
      }
      case 'rule': return `🔗 URL: ${a.assignable_id}`;
      default: return a.assignable_type;
    }
  };

  const sorted = [...(assignments || [])].filter(a => a.assignable_type !== 'default').sort((a, b) => a.priority - b.priority);

  if (isLoading) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  return (
    <div>
      <div className="flex items-center gap-3 mb-6">
        <Link to={`/sites/${siteId}/grids`} className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
          <ArrowLeft className="h-5 w-5" />
        </Link>
        <div className="flex-1">
          <h1 className="text-2xl font-bold text-gray-900">Grid Assignments</h1>
          <p className="mt-1 text-sm text-gray-500">Which grid layout wraps each type of content</p>
        </div>
        <div className="flex items-center gap-2">
          {rebuildMsg && <span className="text-xs text-green-600">{rebuildMsg}</span>}
          <button onClick={triggerRebuild} disabled={rebuilding}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-50 disabled:opacity-50">
            {rebuilding ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
            Rebuild Site
          </button>
          <button onClick={() => { setShowAdd(true); setError(''); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            <Plus className="h-4 w-4" /> Add Rule
          </button>
        </div>
      </div>

      {/* Homepage grid — always visible at top */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6">
        <div className="flex items-center gap-3 mb-3">
          <Home className="h-5 w-5 text-blue-600" />
          <div>
            <h3 className="font-semibold text-blue-900">Homepage Grid</h3>
            <p className="text-xs text-blue-600">{homepage ? `"${homepage.title}" (/${homepage.slug})` : 'No homepage set — go to Settings → Front Page'}</p>
          </div>
        </div>
        {homepage && gridList && gridList.length > 0 && (
          <div className="flex items-center gap-3">
            <select
              value={homepageAssignment?.grid_id || ''}
              onChange={e => {
                const gridId = e.target.value;
                if (!gridId) return;
                if (homepageAssignment) {
                  updateMut.mutate({ id: homepageAssignment.id, data: { grid_id: gridId } });
                } else {
                  createMut.mutate({ grid_id: gridId, assignable_type: 'page', assignable_id: homepage.id, priority: 1 });
                }
              }}
              className="px-3 py-2 border border-blue-200 rounded-lg text-sm bg-white flex-1">
              <option value="">Same as default ({defaultAssignment?.grid?.name || '?'})</option>
              {gridList.map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
            </select>
            {homepageAssignment && (
              <button onClick={() => deleteMut.mutate(homepageAssignment.id)}
                className="text-xs text-blue-400 hover:text-blue-600">Reset to default</button>
            )}
          </div>
        )}
      </div>

      {/* Default grid — always visible */}
      {defaultAssignment && gridList && (
        <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-6">
          <div className="flex items-center gap-3 mb-3">
            <Shield className="h-5 w-5 text-gray-500" />
            <div>
              <h3 className="font-semibold text-gray-700">Default Grid</h3>
              <p className="text-xs text-gray-500">Used for all content that doesn't match any rule below</p>
            </div>
          </div>
          <select value={defaultAssignment.grid_id}
            onChange={e => updateMut.mutate({ id: defaultAssignment.id, data: { grid_id: e.target.value } })}
            className="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white w-full max-w-xs">
            {gridList.map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
          </select>
        </div>
      )}

      {/* Assignment rules */}
      {sorted.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
          <div className="px-4 py-3 bg-gray-50 border-b">
            <h3 className="text-sm font-semibold text-gray-700">Content Rules (checked top to bottom, first match wins)</h3>
          </div>
          <div className="divide-y divide-gray-100">
            {sorted.map((a, i) => (
              <div key={a.id} className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
                <span className="text-xs text-gray-400 w-6 text-center font-mono">{i + 1}</span>
                <div className="flex-1 text-sm text-gray-700">{getLabel(a)}</div>
                <select value={a.grid_id}
                  onChange={e => updateMut.mutate({ id: a.id, data: { grid_id: e.target.value } })}
                  className="px-2 py-1 text-sm border rounded-md w-40">
                  {gridList?.map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
                </select>
                <button onClick={() => updateMut.mutate({ id: a.id, data: { is_active: !a.is_active } })}
                  className={`w-8 h-5 rounded-full ${a.is_active ? 'bg-green-500' : 'bg-gray-300'}`}>
                  <span className={`block w-3.5 h-3.5 bg-white rounded-full shadow ${a.is_active ? 'translate-x-3.5' : 'translate-x-0.5'}`} />
                </button>
                <button onClick={() => updateMut.mutate({ id: a.id, data: { priority: Math.max(1, a.priority - 1) } })}
                  disabled={i === 0} className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"><ArrowUp size={14} /></button>
                <button onClick={() => updateMut.mutate({ id: a.id, data: { priority: a.priority + 1 } })}
                  disabled={i === sorted.length - 1} className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"><ArrowDown size={14} /></button>
                <button onClick={() => deleteMut.mutate(a.id)}
                  className="p-1 text-gray-400 hover:text-red-500"><Trash2 size={14} /></button>
              </div>
            ))}
          </div>
        </div>
      )}

      {sorted.length === 0 && (
        <div className="text-center py-8 text-gray-400 bg-white rounded-xl border border-gray-200">
          <p className="text-sm mb-1">No custom rules yet</p>
          <p className="text-xs">All content uses the default grid. Add rules to assign different grids per category, content type, or page.</p>
        </div>
      )}

      {/* Add rule dialog */}
      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowAdd(false)}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Add Assignment Rule</h3>

            {error && <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">When content is...</label>
                <select value={newType} onChange={e => { setNewType(e.target.value); setNewId(''); }}
                  className="w-full px-3 py-2 border rounded-lg text-sm">
                  <option value="category">In a specific category</option>
                  <option value="post_type">A content type (all posts or all pages)</option>
                  <option value="page">A specific page</option>
                  <option value="rule">Matching a URL pattern</option>
                </select>
              </div>

              {newType === 'post_type' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Content type</label>
                  <div className="flex gap-2">
                    <button onClick={() => setNewId('post')}
                      className={`flex-1 px-3 py-2 text-sm border rounded-lg ${newId === 'post' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'hover:bg-gray-50'}`}>
                      📝 All blog posts
                    </button>
                    <button onClick={() => setNewId('page')}
                      className={`flex-1 px-3 py-2 text-sm border rounded-lg ${newId === 'page' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'hover:bg-gray-50'}`}>
                      📄 All pages
                    </button>
                  </div>
                </div>
              )}

              {newType === 'category' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
                  <select value={newId} onChange={e => setNewId(e.target.value)}
                    className="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select category...</option>
                    {catList?.map(c => <option key={c.id} value={c.id}>{c.name} (/{c.slug})</option>)}
                  </select>
                </div>
              )}

              {newType === 'page' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Page</label>
                  <select value={newId} onChange={e => setNewId(e.target.value)}
                    className="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select page...</option>
                    {pageList?.map(p => <option key={p.id} value={p.id}>{p.title} (/{p.slug})</option>)}
                  </select>
                </div>
              )}

              {newType === 'rule' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">URL pattern</label>
                  <input value={newId} onChange={e => setNewId(e.target.value)}
                    placeholder="/blog/*" className="w-full px-3 py-2 border rounded-lg text-sm" />
                  <p className="text-xs text-gray-400 mt-1">Use * as wildcard.</p>
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Use this grid</label>
                <select value={newGridId} onChange={e => setNewGridId(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg text-sm">
                  <option value="">Select grid...</option>
                  {gridList?.map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
                </select>
              </div>
            </div>

            <div className="flex justify-end gap-2 mt-6">
              <button onClick={() => setShowAdd(false)}
                className="px-4 py-2 text-sm text-gray-700 border rounded-lg hover:bg-gray-50">Cancel</button>
              <button
                onClick={() => {
                  if (!newGridId) { setError('Select a grid'); return; }
                  if (!newId) { setError('Select a condition'); return; }
                  createMut.mutate({
                    grid_id: newGridId,
                    assignable_type: newType,
                    assignable_id: newId,
                    priority: sorted.length + 2,
                  });
                }}
                disabled={createMut.isPending}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {createMut.isPending ? 'Saving...' : 'Add Rule'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
