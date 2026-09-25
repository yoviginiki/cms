import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Loader2, Users as UsersIcon, Copy, Pencil, Globe, KeyRound, Mail, RefreshCw } from 'lucide-react';
import { api, auth, sites as sitesApi } from '@/lib/api';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

type Role = 'viewer' | 'author' | 'editor' | 'admin';

interface SiteGrant {
  site_id: string;
  name: string;
  slug: string;
  role: Role;
}

interface UserData {
  id: string;
  name: string;
  email: string;
  role: string;
  restricted_to_sites: boolean;
  sites: SiteGrant[];
  status: string;
  invitation_expired?: boolean;
  last_login_at: string | null;
  created_at: string;
}

interface SiteOption {
  id: string;
  name: string;
  slug: string;
}

const ROLES: { value: Role; label: string; hint: string }[] = [
  { value: 'viewer', label: 'Viewer', hint: 'само преглед' },
  { value: 'author', label: 'Author', hint: 'собствени страници/постове' },
  { value: 'editor', label: 'Editor', hint: 'редакция + публикуване' },
  { value: 'admin', label: 'Admin', hint: 'настройки, тема, всичко' },
];

const roleColors: Record<string, string> = {
  owner: 'bg-purple-500/15 text-purple-400',
  admin: 'bg-blue-500/15 text-blue-400',
  editor: 'bg-green-500/15 text-green-400',
  author: 'bg-yellow-500/15 text-yellow-500',
  viewer: 'bg-base-content/10 text-base-content/60',
};

function RoleBadge({ role }: { role: string }) {
  return <span className={`px-2 py-0.5 text-[11px] font-medium rounded-full ${roleColors[role] ?? roleColors.viewer}`}>{role}</span>;
}

function generatePassword(): string {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  const bytes = new Uint32Array(14);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}

function errorMessage(err: unknown): string {
  const data = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data;
  const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
  return first || data?.message || 'Something went wrong.';
}

// ─── Create / edit dialog ─────────────────────────────────────────────

interface FormState {
  name: string;
  email: string;
  signIn: 'password' | 'invite';
  password: string;
  restricted: boolean;
  role: Role;
  sites: Record<string, Role>; // site_id → role
}

function UserDialog({
  user,
  siteOptions,
  isOwner,
  profileOnly,
  onClose,
  onInviteLink,
}: {
  user: UserData | null; // null = new user
  siteOptions: SiteOption[];
  isOwner: boolean;
  profileOnly: boolean; // own account / the owner: name, e-mail, password only
  onClose: () => void;
  onInviteLink: (url: string) => void;
}) {
  const queryClient = useQueryClient();
  const dialogRef = useRef<HTMLDialogElement>(null);
  const isNew = user === null;

  const [form, setForm] = useState<FormState>(() => ({
    name: user?.name ?? '',
    email: user?.email ?? '',
    signIn: 'password',
    password: isNew ? generatePassword() : '',
    restricted: user?.restricted_to_sites ?? true,
    role: ((user && user.role !== 'owner' ? user.role : 'editor') as Role),
    sites: Object.fromEntries((user?.sites ?? []).map((s) => [s.site_id, s.role])),
  }));
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => { dialogRef.current?.showModal(); }, []);

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((f) => ({ ...f, [key]: value }));
  const toggleSite = (id: string) => setForm((f) => {
    const next = { ...f.sites };
    if (next[id]) delete next[id]; else next[id] = 'editor';
    return { ...f, sites: next };
  });

  const roleOptions = ROLES.filter((r) => r.value !== 'admin' || isOwner);

  const save = useMutation({
    mutationFn: async () => {
      const access = form.restricted
        ? { restricted_to_sites: true, role: 'editor', sites: Object.entries(form.sites).map(([site_id, role]) => ({ site_id, role })) }
        : { restricted_to_sites: false, role: form.role };
      const body: Record<string, unknown> = { name: form.name, email: form.email, ...(profileOnly ? {} : access) };

      if (!isNew) {
        if (form.password) body.password = form.password;
        return api.put(`/users/${user!.id}`, body);
      }
      if (form.signIn === 'invite') return api.post('/users/invite', body);
      return api.post('/users', { ...body, password: form.password });
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      const url = res.data?.data?.invite_url;
      if (url) onInviteLink(url);
      onClose();
    },
    onError: (err) => setError(errorMessage(err)),
  });

  const canSave = form.name.trim() && form.email.trim()
    && (profileOnly || !form.restricted || Object.keys(form.sites).length > 0)
    && (!isNew || form.signIn === 'invite' || form.password.length >= 8)
    && (isNew || !form.password || form.password.length >= 8);

  return (
    <dialog ref={dialogRef} className="modal" onClose={onClose}>
      <div className="modal-box bg-base-100 border border-base-300/50 max-w-xl">
        <h3 className="text-sm font-medium text-base-content">{isNew ? 'New user' : `Edit ${user!.name}`}</h3>

        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label className="block">
            <span className="text-[11px] text-base-content/50">Name</span>
            <input className="input input-sm input-bordered w-full mt-1" value={form.name} onChange={(e) => set('name', e.target.value)} autoFocus />
          </label>
          <label className="block">
            <span className="text-[11px] text-base-content/50">Email (used to sign in)</span>
            <input type="email" className="input input-sm input-bordered w-full mt-1" value={form.email} onChange={(e) => set('email', e.target.value)} />
          </label>
        </div>

        {/* Sign-in */}
        {isNew && (
          <div className="mt-4">
            <div className="join">
              <button type="button" onClick={() => set('signIn', 'password')} className={`join-item btn btn-xs ${form.signIn === 'password' ? 'btn-primary' : 'btn-ghost'}`}>
                <KeyRound size={12} /> Set password now
              </button>
              <button type="button" onClick={() => set('signIn', 'invite')} className={`join-item btn btn-xs ${form.signIn === 'invite' ? 'btn-primary' : 'btn-ghost'}`}>
                <Mail size={12} /> Send invitation
              </button>
            </div>
          </div>
        )}
        {(form.signIn === 'password' || !isNew) && (
          <label className="block mt-3">
            <span className="text-[11px] text-base-content/50">{isNew ? 'Password (min. 8 characters)' : 'New password (leave empty to keep the current one)'}</span>
            <div className="flex gap-2 mt-1">
              <input className="input input-sm input-bordered w-full font-mono" value={form.password} onChange={(e) => set('password', e.target.value)} autoComplete="new-password" />
              <button type="button" title="Generate" onClick={() => set('password', generatePassword())} className="btn btn-sm btn-ghost"><RefreshCw size={13} /></button>
              <button type="button" title="Copy" disabled={!form.password}
                onClick={() => { navigator.clipboard.writeText(form.password); setCopied(true); setTimeout(() => setCopied(false), 1200); }}
                className="btn btn-sm btn-ghost">{copied ? '✓' : <Copy size={13} />}</button>
            </div>
          </label>
        )}

        {/* Access */}
        {!profileOnly && (
        <div className="mt-5">
          <span className="text-[11px] uppercase tracking-wide text-base-content/40">Access</span>
          <div className="mt-2 flex flex-col gap-2">
            <label className="flex items-center gap-2 text-[13px] cursor-pointer">
              <input type="radio" className="radio radio-xs" checked={form.restricted} onChange={() => set('restricted', true)}/>
              Only selected sites
            </label>
            <label className="flex items-center gap-2 text-[13px] cursor-pointer">
              <input type="radio" className="radio radio-xs" checked={!form.restricted} onChange={() => set('restricted', false)}/>
              All sites
            </label>
          </div>

          {!form.restricted && (
            <div className="mt-3 flex items-center gap-2">
              <span className="text-[12px] text-base-content/60">Role on every site:</span>
              <select className="select select-xs select-bordered" value={form.role} onChange={(e) => set('role', e.target.value as Role)}>
                {roleOptions.map((r) => <option key={r.value} value={r.value}>{r.label} — {r.hint}</option>)}
              </select>
            </div>
          )}

          {form.restricted && (
            <div className="mt-3 max-h-64 overflow-y-auto rounded-lg border border-base-300/50 divide-y divide-base-300/40">
              {siteOptions.length === 0 && <div className="p-3 text-[12px] text-base-content/50">No sites yet.</div>}
              {siteOptions.map((site) => {
                const role = form.sites[site.id];
                return (
                  <div key={site.id} className="flex items-center gap-3 px-3 py-2">
                    <input type="checkbox" className="checkbox checkbox-xs" checked={!!role} onChange={() => toggleSite(site.id)} id={`site-${site.id}`} />
                    <label htmlFor={`site-${site.id}`} className="flex-1 min-w-0 cursor-pointer">
                      <div className="text-[13px] text-base-content truncate">{site.name}</div>
                      <div className="text-[11px] text-base-content/40 truncate">{site.slug}</div>
                    </label>
                    {role && (
                      <select className="select select-xs select-bordered" value={role}
                        onChange={(e) => setForm((f) => ({ ...f, sites: { ...f.sites, [site.id]: e.target.value as Role } }))}>
                        {roleOptions.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                        {role === 'admin' && !isOwner && <option value="admin">Admin</option>}
                      </select>
                    )}
                  </div>
                );
              })}
            </div>
          )}
          <p className="mt-2 text-[11px] text-base-content/40 leading-relaxed">
            {form.restricted
              ? 'The user sees only the checked sites, with the role picked for each. Team, modules and system settings stay closed to them.'
              : 'The user sees every site in the account, now and in the future.'}
          </p>
        </div>
        )}

        {error && <div className="mt-4 rounded-md bg-error/10 border border-error/30 px-3 py-2 text-[12px] text-error">{error}</div>}

        <div className="modal-action mt-6">
          <button onClick={onClose} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
          <button onClick={() => { setError(''); save.mutate(); }} disabled={!canSave || save.isPending} className="btn btn-primary btn-sm text-[12px]">
            {save.isPending && <Loader2 size={12} className="animate-spin" />}
            {isNew ? (form.signIn === 'invite' ? 'Create & invite' : 'Create user') : 'Save'}
          </button>
        </div>
      </div>
      <form method="dialog" className="modal-backdrop"><button>close</button></form>
    </dialog>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────

export default function Users() {
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<UserData | null>(null);
  const [inviteUrl, setInviteUrl] = useState('');
  const [editing, setEditing] = useState<UserData | 'new' | null>(null);

  const { data, isLoading, error } = useQuery<UserData[]>({
    queryKey: ['users'],
    queryFn: () => api.get('/users').then(r => r.data.data),
  });

  const { data: siteOptions = [] } = useQuery<SiteOption[]>({
    queryKey: ['sites', 'user-access-options'],
    queryFn: () => sitesApi.list().then(r => r.data.data.map((s: SiteOption) => ({ id: s.id, name: s.name, slug: s.slug }))),
  });

  const { data: me } = useQuery<{ id: string; role: string }>({
    queryKey: ['auth', 'me'],
    queryFn: () => auth.me().then(r => r.data.user),
  });
  const isOwner = me?.role === 'owner';

  // F10: pending invitations can be re-sent (new link, old one dies) or revoked.
  const resendMutation = useMutation({
    mutationFn: (id: string) => api.post(`/users/${id}/invite/resend`),
    onSuccess: (res) => { setInviteUrl(res.data.data.invite_url); queryClient.invalidateQueries({ queryKey: ['users'] }); },
  });
  const revokeMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/users/${id}/invite`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['users'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/users/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setDeleteTarget(null);
    },
  });

  const canManage = (u: UserData) => u.role !== 'owner' && u.id !== me?.id && (u.role !== 'admin' || isOwner);

  const sorted = useMemo(() => data ?? [], [data]);

  return (
    <div className="max-w-5xl mx-auto py-8 px-4">
      <div className="flex items-center justify-between mb-8 gap-4">
        <div>
          <h1 className="text-xl font-semibold text-base-content">Team Members</h1>
          <p className="mt-1 text-[13px] text-base-content/50">Create users and choose which sites each of them can work on</p>
        </div>
        <button onClick={() => setEditing('new')} className="btn btn-primary btn-sm text-[12px]">
          <Plus className="h-4 w-4" />
          New user
        </button>
      </div>

      {inviteUrl && (
        <div className="mb-6 rounded-lg bg-success/10 border border-success/30 p-4">
          <p className="text-[13px] font-medium text-success mb-2">Invitation created! Share this link:</p>
          <div className="flex items-center gap-2">
            <input type="text" readOnly value={inviteUrl} className="input input-sm input-bordered flex-1" onClick={(e) => (e.target as HTMLInputElement).select()} />
            <button onClick={() => { navigator.clipboard.writeText(inviteUrl); }} className="btn btn-ghost btn-sm"><Copy className="h-4 w-4" /></button>
          </div>
          <button onClick={() => setInviteUrl('')} className="mt-2 text-[11px] text-success hover:underline">Dismiss</button>
        </div>
      )}

      {isLoading && <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/30" /></div>}
      {error && <div className="rounded-lg bg-error/10 border border-error/30 p-4 text-[13px] text-error">Failed to load users.</div>}

      {data && data.length === 0 && (
        <EmptyState icon={UsersIcon} title="No team members" description="Create your first team member" actionLabel="New user" onAction={() => setEditing('new')} />
      )}

      {sorted.length > 0 && (
        <div className="rounded-xl border border-base-300/50 overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead className="bg-base-200/50 border-b border-base-300/50">
              <tr>
                <th className="text-left px-4 py-2.5 font-medium text-base-content/50">User</th>
                <th className="text-left px-4 py-2.5 font-medium text-base-content/50">Access</th>
                <th className="text-center px-4 py-2.5 font-medium text-base-content/50">Status</th>
                <th className="text-right px-4 py-2.5 font-medium text-base-content/50">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-base-300/40">
              {sorted.map((user) => (
                <tr key={user.id} className="hover:bg-base-200/30 align-top">
                  <td className="px-4 py-3">
                    <div className="font-medium text-base-content">{user.name}</div>
                    <div className="text-[12px] text-base-content/50">{user.email}</div>
                  </td>
                  <td className="px-4 py-3">
                    {user.restricted_to_sites ? (
                      <div className="flex flex-wrap gap-1.5">
                        {user.sites.map((s) => (
                          <span key={s.site_id} className="inline-flex items-center gap-1.5 rounded-md border border-base-300/60 px-2 py-0.5 text-[12px]">
                            <span className="text-base-content/80">{s.name}</span>
                            <RoleBadge role={s.role} />
                          </span>
                        ))}
                        {user.sites.length === 0 && <span className="text-[12px] text-warning">no sites</span>}
                      </div>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 text-[12px] text-base-content/70">
                        <Globe size={12} /> All sites <RoleBadge role={user.role} />
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    <span className={`px-2 py-0.5 text-[11px] font-medium rounded-full ${user.status === 'active' ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning'}`}>
                      {user.status === 'pending' && user.invitation_expired ? 'expired' : user.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right whitespace-nowrap">
                    {user.status === 'pending' && canManage(user) && (
                      <>
                        <button onClick={() => resendMutation.mutate(user.id)} disabled={resendMutation.isPending} className="btn btn-ghost btn-xs text-[11px]" title="Send a new invitation link">Resend</button>
                        <button onClick={() => { if (window.confirm(`Revoke the invitation for ${user.email}?`)) revokeMutation.mutate(user.id); }} className="btn btn-ghost btn-xs text-[11px]" title="Revoke the invitation">Revoke</button>
                      </>
                    )}
                    {(canManage(user) || user.id === me?.id) && (
                      <button onClick={() => setEditing(user)} className="btn btn-ghost btn-xs" title="Edit"><Pencil className="h-3.5 w-3.5" /></button>
                    )}
                    {canManage(user) && user.status !== 'pending' && (
                      <button onClick={() => setDeleteTarget(user)} className="btn btn-ghost btn-xs hover:text-error" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {editing && (
        <UserDialog
          user={editing === 'new' ? null : editing}
          siteOptions={siteOptions}
          isOwner={isOwner}
          profileOnly={editing !== 'new' && (editing.role === 'owner' || editing.id === me?.id)}
          onClose={() => setEditing(null)}
          onInviteLink={setInviteUrl}
        />
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
