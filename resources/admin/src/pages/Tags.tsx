import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Plus, Trash2, Loader2, Hash } from 'lucide-react';
import { tags } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Tag {
  id: string;
  name: string;
  slug: string;
  posts_count: number;
}

export default function Tags() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<Tag | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState('');

  const { data, isLoading, error } = useQuery<Tag[]>({
    queryKey: ['tags', siteId],
    queryFn: () => tags.list(siteId).then(r => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (name: string) => tags.create(siteId, { name }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tags', siteId] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) => tags.update(siteId, id, { name }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tags', siteId] });
      setEditingId(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => tags.delete(siteId, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tags', siteId] });
      setDeleteTarget(null);
    },
  });

  const handleCreate = () => {
    const name = window.prompt('Tag name:');
    if (name?.trim()) createMutation.mutate(name.trim());
  };

  const startEdit = (tag: Tag) => {
    setEditingId(tag.id);
    setEditName(tag.name);
  };

  const saveEdit = (id: string) => {
    if (editName.trim()) {
      updateMutation.mutate({ id, name: editName.trim() });
    }
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Tags</h1>
          <p className="mt-1 text-sm text-gray-500">Organize posts with tags</p>
        </div>
        <button
          onClick={handleCreate}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
        >
          <Plus className="h-4 w-4" />
          New Tag
        </button>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          Failed to load tags.
        </div>
      )}

      {data && data.length === 0 && (
        <EmptyState
          icon={Hash}
          title="No tags yet"
          description="Create your first tag to organize posts"
          actionLabel="New Tag"
          onAction={handleCreate}
        />
      )}

      {data && data.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-6 py-3 font-medium text-gray-500">Name</th>
                <th className="text-left px-6 py-3 font-medium text-gray-500">Slug</th>
                <th className="text-center px-6 py-3 font-medium text-gray-500">Posts</th>
                <th className="text-right px-6 py-3 font-medium text-gray-500">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.map((tag) => (
                <tr key={tag.id} className="hover:bg-gray-50">
                  <td className="px-6 py-3">
                    {editingId === tag.id ? (
                      <input
                        value={editName}
                        onChange={(e) => setEditName(e.target.value)}
                        onBlur={() => saveEdit(tag.id)}
                        onKeyDown={(e) => e.key === 'Enter' && saveEdit(tag.id)}
                        className="px-2 py-1 border border-blue-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        autoFocus
                      />
                    ) : (
                      <button
                        onClick={() => startEdit(tag)}
                        className="font-medium text-gray-900 hover:text-blue-600"
                      >
                        {tag.name}
                      </button>
                    )}
                  </td>
                  <td className="px-6 py-3 text-gray-500">{tag.slug}</td>
                  <td className="px-6 py-3 text-center text-gray-500">{tag.posts_count}</td>
                  <td className="px-6 py-3 text-right">
                    <button
                      onClick={() => setDeleteTarget(tag)}
                      className="p-1 text-gray-400 hover:text-red-500 rounded"
                      title="Delete"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete tag"
        message={`Delete "${deleteTarget?.name}"? Posts with this tag will be untagged.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
