import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { api } from '@/lib/api';

/**
 * Invitation acceptance (F10): the link from the invitation email lands here,
 * validates the token, lets the person choose a password, then sends them to
 * sign in. Expired/used/wrong tokens show a clear message.
 */
export default function InviteAccept() {
  const { token = '' } = useParams();
  const [state, setState] = useState<'loading' | 'ready' | 'invalid' | 'done'>('loading');
  const [info, setInfo] = useState<{ name: string; email: string; expires_at: string | null } | null>(null);
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api.get(`/auth/invite/${encodeURIComponent(token)}`)
      .then((r) => { if (!cancelled) { setInfo(r.data.data); setState('ready'); } })
      .catch(() => { if (!cancelled) setState('invalid'); });
    return () => { cancelled = true; };
  }, [token]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (password.length < 8) { setError('Password must be at least 8 characters.'); return; }
    if (password !== confirm) { setError('Passwords do not match.'); return; }
    setSaving(true);
    try {
      await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
      await api.post(`/auth/invite/${encodeURIComponent(token)}/accept`, { password, password_confirmation: confirm });
      setState('done');
    } catch (err: any) {
      const status = err.response?.status;
      setError(status === 410 ? 'This invitation is invalid, already used or has expired.' : (err.response?.data?.message || 'Could not accept the invitation.'));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-base-200 px-4" data-theme="cms-admin">
      <div className="w-full max-w-xs">
        <div className="text-center mb-8">
          <h1 className="text-lg font-medium text-base-content/90 tracking-tight">cms</h1>
          <p className="mt-1 text-[13px] text-base-content/40">accept your invitation</p>
        </div>
        <div className="card bg-base-100 border border-base-300/40 shadow-elev-2">
          <div className="card-body p-5 gap-4">
            {state === 'loading' && <div className="flex justify-center py-6"><Loader2 className="h-4 w-4 animate-spin text-base-content/30" /></div>}
            {state === 'invalid' && (
              <div className="text-[13px] text-base-content/70">
                <p className="font-medium text-error mb-1">This invitation is not valid</p>
                <p>It may have expired or already been used. Ask an administrator to send a new one.</p>
              </div>
            )}
            {state === 'done' && (
              <div className="text-[13px] text-base-content/70">
                <p className="font-medium text-success mb-1">Your account is ready</p>
                <Link to="/login" className="btn btn-primary btn-sm w-full mt-3 text-[12px]">sign in</Link>
              </div>
            )}
            {state === 'ready' && info && (
              <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                <p className="text-[13px] text-base-content/70">Hello <b>{info.name}</b> ({info.email}). Choose a password to finish.</p>
                {error && <div className="alert alert-error text-[12px] py-2 px-3">{error}</div>}
                <fieldset className="fieldset">
                  <label className="fieldset-label text-[12px] text-base-content/50 mb-1">Password</label>
                  <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" className="input input-bordered input-sm w-full text-[13px]" />
                </fieldset>
                <fieldset className="fieldset">
                  <label className="fieldset-label text-[12px] text-base-content/50 mb-1">Confirm password</label>
                  <input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required minLength={8} autoComplete="new-password" className="input input-bordered input-sm w-full text-[13px]" />
                </fieldset>
                <button type="submit" disabled={saving} className="btn btn-primary btn-sm w-full text-[12px]">
                  {saving && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  {saving ? 'saving...' : 'set password'}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
