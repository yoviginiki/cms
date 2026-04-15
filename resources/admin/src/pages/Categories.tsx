import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Plus, Edit, Trash2, FolderTree, Loader2, Check, X } from 'lucide-react';
import { categories } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Category {
  id: string;
  name: string;
  slug: string;
  parent_id: string | null;
  posts_count?: number;
}

export default function Categories() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState('');
  const [newName, setNewName] = useState('');
  const [newParent, setNewParent] = useState<string>('');
  const [showAddForm, setShowAddForm] = useState(false);

  const { data, isLoading, error } = useQuery<Category[]>({
    queryKey: ['categories', siteId],
    queryFn: () => categories.list(siteId).then(r => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (data: { name: string; parent_id?: string }) =>
      categories.create(siteId, { ...data, slug: data.name.toLowerCase().replace(/\s+/g, '-') }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories', siteId] });
      setNewName('');
      setNewParent('');
      setShowAddForm(false);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) =>
      categories.update(siteId, id, { name, slug: name.toLowerCase().replace(/\s+/g, '-') }),
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

  // Calculate depth for indentation
  const getDepth = (cat: Category, allCats: Category[]): number => {
    if (!cat.parent_id) return 0;
    const parent = allCats.find(c => c.id === cat.parent_id);
    return parent ? 1 + getDepth(parent, allCats) : 0;
  };

  // Sort categories: parents first, then children grouped under parents
  const sortedCategories = (data || []).slice().sort((a, b) => {
    const depthA = getDepth(a, data || []);
    const depthB = getDepth(b, data || []);
    if (depthA !== depthB) return depthA - depthB;
    return a.name.localeCompare(b.name);
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
  };

  const saveEdit = () => {
    if (!editingId || !editName.trim()) return;
    updateMutation.mutate({ id: editingId, name: editName.trim() });
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Categories</h1>
          <p className="mt-1 text-sm text-gray-500">Organize your posts into categories</p>
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
            <div className="w-48">
              <label className="block text-sm font-medium text-gray-700 mb-1">Parent</label>
              <select
                value={newParent}
                onChange={(e) => setNewParent(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">None (top-level)</option>
                {data?.filter(c => !c.parent_id).map(c => (
                  <option key={c.id} value={c.id}>{c.name}</option>
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

      {data && data.length === 0 && (
        <EmptyState
          icon={FolderTree}
          title="No categories yet"
          description="Create categories to organize your posts"
          actionLabel="Add Category"
          onAction={() => setShowAddForm(true)}
        />
      )}

      {data && data.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <ul className="divide-y divide-gray-100">
            {sortedCategories.map((cat) => {
              const depth = getDepth(cat, data);
              const isEditing = editingId === cat.id;

              return (
                <li key={cat.id} className="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 transition-colors">
                  <div className="flex-1 flex items-center" style={{ paddingLeft: `${depth * 24}px` }}>
                    {isEditing ? (
                      <div className="flex items-center gap-2 flex-1">
                        <input
                          type="text"
                          value={editName}
                          onChange={(e) => setEditName(e.target.value)}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') saveEdit();
                            if (e.key === 'Escape') setEditingId(null);
                          }}
                          className="flex-1 px-3 py-1 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          autoFocus
                        />
                        <button
                          onClick={saveEdit}
                          className="p-1 text-green-600 hover:bg-green-50 rounded"
                          title="Save"
                        >
                          <Check className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => setEditingId(null)}
                          className="p-1 text-gray-400 hover:bg-gray-100 rounded"
                          title="Cancel"
                        >
                          <X className="h-4 w-4" />
                        </button>
                      </div>
                    ) : (
                      <>
                        <span className="font-medium text-gray-900">{cat.name}</span>
                        <span className="ml-3 text-sm text-gray-400">/{cat.slug}</span>
                      </>
                    )}
                  </div>
                  {!isEditing && (
                    <>
                      <span className="text-sm text-gray-500">
                        {cat.posts_count ?? 0} posts
                      </span>
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => startEdit(cat)}
                          className="p-2 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
                          title="Edit"
                        >
                          <Edit className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => setDeleteTarget(cat)}
                          className="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                          title="Delete"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </>
                  )}
                </li>
              );
            })}
          </ul>
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
