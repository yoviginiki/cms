import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Loader2, Users as UsersIcon, Copy } from 'lucide-react';
import { api } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface UserData {
  id: string;
  name: string;
  email: string;
  role: string;
  status: string;
  last_login_at: string | null;
  created_at: string;
}

const roleColors: Record<string, string> = {
  owner: 'bg-purple-100 text-purple-700',
  admin: 'bg-blue-100 text-blue-700',
  editor: 'bg-green-100 text-green-700',
  author: 'bg-yellow-100 text-yellow-700',
  viewer: 'bg-gray-100 text-gray-700',
};

export default function Users() {
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<UserData | null>(null);
  const [inviteUrl, setInviteUrl] = useState('');

  const { data, isLoading, error } = useQuery<UserData[]>({
    queryKey: ['users'],
    queryFn: () => api.get('/users').then(r => r.data.data),
  });

  const inviteMutation = useMutation({
    mutationFn: (data: { name: string; email: string; role: string }) => api.post('/users/invite', data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setInviteUrl(res.data.data.invite_url);
    },
  });

  const updateRoleMutation = useMutation({
    mutationFn: ({ id, role }: { id: string; role: string }) => api.put(`/users/${id}/role`, { role }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['users'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/users/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setDeleteTarget(null);
    },
  });

  const handleInvite = () => {
    const name = window.prompt('Full name:');
    if (!name) return;
    const email = window.prompt('Email address:');
    if (!email) return;
    const role = window.prompt('Role (editor / admin / author / viewer):', 'editor') || 'editor';
    inviteMutation.mutate({ name, email, role });
  };

  return (
    <div className="max-w-4xl mx-auto py-8 px-4">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Team Members</h1>
          <p className="mt-1 text-sm text-gray-500">Manage users and permissions</p>
        </div>
        <button onClick={handleInvite} className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
          <Plus className="h-4 w-4" />
          Invite User
        </button>
      </div>

      {inviteUrl && (
        <div className="mb-6 rounded-lg bg-green-50 border border-green-200 p-4">
          <p className="text-sm font-medium text-green-800 mb-2">Invitation created! Share this link:</p>
          <div className="flex items-center gap-2">
            <input type="text" readOnly value={inviteUrl} className="flex-1 px-3 py-1.5 text-sm border border-green-200 rounded bg-white" onClick={(e) => (e.target as HTMLInputElement).select()} />
            <button onClick={() => { navigator.clipboard.writeText(inviteUrl); }} className="p-2 text-green-600 hover:bg-green-100 rounded"><Copy className="h-4 w-4" /></button>
          </div>
          <button onClick={() => setInviteUrl('')} className="mt-2 text-xs text-green-600 hover:underline">Dismiss</button>
        </div>
      )}

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>}
      {error && <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load users.</div>}

      {data && data.length === 0 && (
        <EmptyState icon={UsersIcon} title="No team members" description="Invite your first team member" actionLabel="Invite" onAction={handleInvite} />
      )}

      {data && data.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-6 py-3 font-medium text-gray-500">Name</th>
                <th className="text-left px-6 py-3 font-medium text-gray-500">Email</th>
                <th className="text-center px-6 py-3 font-medium text-gray-500">Role</th>
                <th className="text-center px-6 py-3 font-medium text-gray-500">Status</th>
                <th className="text-right px-6 py-3 font-medium text-gray-500">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.map((user) => (
                <tr key={user.id} className="hover:bg-gray-50">
                  <td className="px-6 py-3 font-medium text-gray-900">{user.name}</td>
                  <td className="px-6 py-3 text-gray-500">{user.email}</td>
                  <td className="px-6 py-3 text-center">
                    {user.role === 'owner' ? (
                      <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${roleColors[user.role]}`}>{user.role}</span>
                    ) : (
                      <select
                        value={user.role}
                        onChange={(e) => updateRoleMutation.mutate({ id: user.id, role: e.target.value })}
                        className="px-2 py-0.5 text-xs border border-gray-200 rounded"
                      >
                        <option value="viewer">Viewer</option>
                        <option value="author">Author</option>
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                      </select>
                    )}
                  </td>
                  <td className="px-6 py-3 text-center">
                    <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${user.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>
                      {user.status}
                    </span>
                  </td>
                  <td className="px-6 py-3 text-right">
                    {user.role !== 'owner' && (
                      <button onClick={() => setDeleteTarget(user)} className="p-1 text-gray-400 hover:text-red-500 rounded"><Trash2 className="h-4 w-4" /></button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Remove user"
        message={`Remove "${deleteTarget?.name}" from your team? They will lose access immediately.`}
        confirmText="Remove"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
