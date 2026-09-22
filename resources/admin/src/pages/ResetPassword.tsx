import { useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { api } from '@/lib/api';

/**
 * Password reset (F09): the emailed link carries token + email; the token
 * is valid for 60 minutes and can be used once.
 */
export default function ResetPassword() {
  const [params] = useSearchParams();
  const token = params.get('token') || '';
  const email = params.get('email') || '';
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState('');
  const [done, setDone] = useState(false);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (password !== confirm) { setError('Passwords do not match.'); return; }
    setSaving(true);
    try {
      await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
      await api.post('/auth/reset-password', { token, email, password, password_confirmation: confirm });
      setDone(true);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Could not reset the password. The link may have expired.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-base-200 px-4" data-theme="cms-admin">
      <div className="w-full max-w-xs">
        <div className="text-center mb-8">
          <h1 className="text-lg font-medium text-base-content/90 tracking-tight">cms</h1>
          <p className="mt-1 text-[13px] text-base-content/40">choose a new password</p>
        </div>
        <div className="card bg-base-100 border border-base-300/40 shadow-elev-2">
          <div className="card-body p-5 gap-4">
            {!token || !email ? (
              <p className="text-[13px] text-error">This reset link is incomplete. Request a new one from the sign-in page.</p>
            ) : done ? (
              <div className="text-[13px] text-base-content/70">
                <p className="font-medium text-success mb-1">Password updated</p>
                <Link to="/login" className="btn btn-primary btn-sm w-full mt-3 text-[12px]">sign in</Link>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                {error && <div className="alert alert-error text-[12px] py-2 px-3">{error}</div>}
                <p className="text-[12px] text-base-content/50">{email}</p>
                <fieldset className="fieldset">
                  <label className="fieldset-label text-[12px] text-base-content/50 mb-1">New password</label>
                  <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" className="input input-bordered input-sm w-full text-[13px]" />
                </fieldset>
                <fieldset className="fieldset">
                  <label className="fieldset-label text-[12px] text-base-content/50 mb-1">Confirm password</label>
                  <input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required minLength={8} autoComplete="new-password" className="input input-bordered input-sm w-full text-[13px]" />
                </fieldset>
                <button type="submit" disabled={saving} className="btn btn-primary btn-sm w-full text-[12px]">
                  {saving && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  {saving ? 'saving...' : 'reset password'}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
