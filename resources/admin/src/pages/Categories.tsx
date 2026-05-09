import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Plus, Edit, Trash2, FolderTree, Loader2, Check, X, ChevronRight, ChevronDown, CornerDownRight, Eye, EyeOff, LayoutGrid } from 'lucide-react';
import { categories } from '@/lib/api';
import api from '@/lib/api';
import { slugify } from '@/lib/slugify';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Category {
  id: string;
  name: string;
  slug: string;
  parent_id: string | null;
  is_public?: boolean;
  grid_id?: string | null;
  posts_count?: number;
  children?: Category[];
}

interface Grid {
  id: string;
  name: string;
}

/** Flatten a nested category tree into a flat array */
function flattenTree(tree: Category[]): Category[] {
  const result: Category[] = [];
  for (const cat of tree) {
    const { children, ...rest } = cat;
    result.push(rest);
    if (children && children.length > 0) {
      result.push(...flattenTree(children));
    }
  }
  return result;
}

/** Build tree from flat array */
function buildTree(flat: Category[]): Category[] {
  const map = new Map<string, Category & { children: Category[] }>();
  const roots: Category[] = [];

  for (const cat of flat) {
    map.set(cat.id, { ...cat, children: [] });
  }
  for (const cat of flat) {
    const node = map.get(cat.id)!;
    if (cat.parent_id && map.has(cat.parent_id)) {
      map.get(cat.parent_id)!.children.push(node);
    } else {
      roots.push(node);
    }
  }
  return roots;
}

export default function Categories() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState('');
  const [editParent, setEditParent] = useState<string>('');
  const [editPublic, setEditPublic] = useState(true);
  const [editGridId, setEditGridId] = useState<string>('');
  const [newName, setNewName] = useState('');
  const [newParent, setNewParent] = useState<string>('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

  const { data: flatData, isLoading, error } = useQuery<Category[]>({
    queryKey: ['categories', siteId],
    queryFn: () => categories.list(siteId).then(r => flattenTree(r.data.data)),
  });

  const tree = flatData ? buildTree(flatData) : [];

  const { data: grids } = useQuery<Grid[]>({
    queryKey: ['grids', siteId],
    queryFn: () => api.get(`/sites/${siteId}/grids`).then(r => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (data: { name: string; parent_id?: string }) =>
      categories.create(siteId, { ...data, slug: slugify(data.name) }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories', siteId] });
      setNewName('');
      setNewParent('');
      setShowAddForm(false);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Record<string, unknown> }) =>
      categories.update(siteId, id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories', siteId] });
      setEditingId(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (catId: string) => categories.delete(siteId, catId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories', siteId] });
      setDeleteTarget(null);
    },
  });

  const handleCreate = () => {
    if (!newName.trim()) return;
    createMutation.mutate({
      name: newName.trim(),
      ...(newParent ? { parent_id: newParent } : {}),
    });
  };

  const startEdit = (cat: Category) => {
    setEditingId(cat.id);
    setEditName(cat.name);
    setEditParent(cat.parent_id || '');
    setEditPublic(cat.is_public !== false);
    setEditGridId(cat.grid_id || '');
  };

  const saveEdit = () => {
    if (!editingId || !editName.trim()) return;
    const original = flatData?.find(c => c.id === editingId);
    const newParentId = editParent || null;
    updateMutation.mutate({
      id: editingId,
      data: {
        name: editName.trim(),
        slug: slugify(editName.trim()),
        is_public: editPublic,
        grid_id: editGridId || null,
        ...(newParentId !== (original?.parent_id ?? null) ? { parent_id: newParentId } : {}),
      },
    });
  };

  const togglePublic = (cat: Category) => {
    updateMutation.mutate({
      id: cat.id,
      data: { is_public: !cat.is_public },
    });
  };

  const toggleCollapse = (id: string) => {
    setCollapsed(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  /** Check if making catId a child of newParentId would be circular */
  const wouldBeCircular = (catId: string, newParentId: string): boolean => {
    if (catId === newParentId) return true;
    let current = flatData?.find(c => c.id === newParentId);
    while (current?.parent_id) {
      if (current.parent_id === catId) return true;
      current = flatData?.find(c => c.id === current!.parent_id);
    }
    return false;
  };

  const renderCategory = (cat: Category & { children?: Category[] }, depth: number): React.ReactNode => {
    const hasChildren = cat.children && cat.children.length > 0;
    const isCollapsed = collapsed.has(cat.id);
    const isEditing = editingId === cat.id;

    return (
      <div key={cat.id}>
        <div
          className={`flex items-center gap-2 px-4 py-2.5 border-b border-gray-100 hover:bg-gray-50 transition-colors ${
            depth > 0 ? 'bg-gray-50/50' : ''
          }`}
          style={{ paddingLeft: `${16 + depth * 28}px` }}
        >
          {/* Expand/collapse toggle */}
          <button
            onClick={() => hasChildren && toggleCollapse(cat.id)}
            className={`w-5 h-5 flex items-center justify-center rounded ${
              hasChildren ? 'text-gray-400 hover:text-gray-600 hover:bg-gray-200' : ''
            }`}
          >
            {hasChildren ? (
              isCollapsed ? <ChevronRight className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />
            ) : depth > 0 ? (
              <CornerDownRight className="h-3 w-3 text-gray-300" />
            ) : null}
          </button>

          {isEditing ? (
            /* Edit mode */
            <div className="flex-1 space-y-2">
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={editName}
                  onChange={(e) => setEditName(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') saveEdit();
                    if (e.key === 'Escape') setEditingId(null);
                  }}
                  className="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  autoFocus
                />
                <button onClick={saveEdit} className="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Save">
                  <Check className="h-4 w-4" />
                </button>
                <button onClick={() => setEditingId(null)} className="p-1.5 text-gray-400 hover:bg-gray-100 rounded-lg" title="Cancel">
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="flex items-center gap-3 text-xs">
                <select
                  value={editParent}
                  onChange={(e) => setEditParent(e.target.value)}
                  className="px-2 py-1 border border-gray-300 rounded text-xs bg-white"
                >
                  <option value="">Top level</option>
                  {flatData?.filter(c => c.id !== cat.id && !wouldBeCircular(cat.id, c.id)).map(c => (
                    <option key={c.id} value={c.id}>
                      {'  '.repeat(getDepth(c, flatData))}
                      {c.name}
                    </option>
                  ))}
                </select>
                <select
                  value={editGridId}
                  onChange={(e) => setEditGridId(e.target.value)}
                  className="px-2 py-1 border border-gray-300 rounded text-xs bg-white"
                >
                  <option value="">Default grid</option>
                  {grids?.map(g => (
                    <option key={g.id} value={g.id}>{g.name}</option>
                  ))}
                </select>
                <label className="flex items-center gap-1 cursor-pointer">
                  <input type="checkbox" checked={editPublic} onChange={(e) => setEditPublic(e.target.checked)}
                    className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                  <span className="text-gray-500">Public</span>
                </label>
              </div>
            </div>
          ) : (
            /* View mode */
            <>
              <div className="flex-1 flex items-center gap-2 min-w-0">
                <span className={`font-medium truncate ${cat.is_public === false ? 'text-gray-400' : 'text-gray-900'}`}>{cat.name}</span>
                <span className="text-xs text-gray-400 truncate">/{cat.slug}</span>
                {cat.is_public === false && (
                  <span className="px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-500 rounded">hidden</span>
                )}
                {cat.grid_id && grids && (
                  <span className="px-1.5 py-0.5 text-[10px] font-medium bg-blue-50 text-blue-600 rounded flex items-center gap-0.5">
                    <LayoutGrid className="h-2.5 w-2.5" />
                    {grids.find(g => g.id === cat.grid_id)?.name || 'Grid'}
                  </span>
                )}
              </div>
              <span className="text-xs text-gray-400 tabular-nums whitespace-nowrap">
                {cat.posts_count ?? 0} posts
              </span>
              <div className="flex items-center gap-0.5">
                <button
                  onClick={() => togglePublic(cat)}
                  className={`p-1.5 rounded-lg transition-colors ${cat.is_public === false ? 'text-gray-300 hover:text-green-600 hover:bg-green-50' : 'text-green-500 hover:text-red-600 hover:bg-red-50'}`}
                  title={cat.is_public === false ? 'Make public' : 'Hide from public'}
                >
                  {cat.is_public === false ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                </button>
                <button
                  onClick={() => startEdit(cat)}
                  className="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
                  title="Edit"
                >
                  <Edit className="h-3.5 w-3.5" />
                </button>
                <button
                  onClick={() => setDeleteTarget(cat)}
                  className="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                  title="Delete"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            </>
          )}
        </div>

        {/* Children */}
        {hasChildren && !isCollapsed && cat.children!.map(child => renderCategory(child, depth + 1))}
      </div>
    );
  };

  const getDepth = (cat: Category, allCats: Category[]): number => {
    if (!cat.parent_id) return 0;
    const parent = allCats.find(c => c.id === cat.parent_id);
    return parent ? 1 + getDepth(parent, allCats) : 0;
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Categories</h1>
          <p className="mt-1 text-sm text-gray-500">
            Organize your posts into categories.
            {flatData && <span className="ml-1 text-gray-400">({flatData.length} total)</span>}
          </p>
        </div>
        <button
          onClick={() => setShowAddForm(!showAddForm)}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
        >
          <Plus className="h-4 w-4" />
          Add Category
        </button>
      </div>

      {/* Add form */}
      {showAddForm && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
          <div className="flex items-end gap-4">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input
                type="text"
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
                placeholder="Category name"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                autoFocus
              />
            </div>
            <div className="w-56">
              <label className="block text-sm font-medium text-gray-700 mb-1">Parent</label>
              <select
                value={newParent}
                onChange={(e) => setNewParent(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">None (top-level)</option>
                {flatData?.map(c => (
                  <option key={c.id} value={c.id}>
                    {'  '.repeat(getDepth(c, flatData))}
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
            <button
              onClick={handleCreate}
              disabled={createMutation.isPending || !newName.trim()}
              className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Add'}
            </button>
            <button
              onClick={() => { setShowAddForm(false); setNewName(''); setNewParent(''); }}
              className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          Failed to load categories. Please try again.
        </div>
      )}

      {flatData && flatData.length === 0 && (
        <EmptyState
          icon={FolderTree}
          title="No categories yet"
          description="Create categories to organize your posts"
          actionLabel="Add Category"
          onAction={() => setShowAddForm(true)}
        />
      )}

      {flatData && flatData.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          {tree.map(cat => renderCategory(cat, 0))}
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete category"
        message={`Are you sure you want to delete "${deleteTarget?.name}"? Posts in this category will become uncategorized.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
