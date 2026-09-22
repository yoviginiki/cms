import { useState } from 'react';
import { Loader2 } from 'lucide-react';
import { api } from '@/lib/api';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [forgot, setForgot] = useState(false);
  const [forgotSent, setForgotSent] = useState(false);

  async function handleForgot(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
      await api.post('/auth/forgot-password', { email });
      setForgotSent(true);
    } catch (err: any) {
      setError(err.response?.status === 429 ? 'Too many attempts. Please wait a minute.' : 'Could not send the reset link. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
      const res = await api.post('/auth/login', { email, password });
      if (res.data?.user) {
        window.location.href = '/admin/dashboard';
      }
    } catch (err: any) {
      const status = err.response?.status;
      const msg = err.response?.data?.message;
      if (status === 419) {
        setError('Session expired. Please refresh the page and try again.');
      } else if (status === 401) {
        setError('Invalid email or password.');
      } else if (status === 429) {
        setError('Too many login attempts. Please wait a minute.');
      } else {
        setError(msg || `Login failed (${status || 'network error'}). Please try again.`);
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-base-200 px-4" data-theme="cms-admin">
      <div className="w-full max-w-xs">
        <div className="text-center mb-8">
          <h1 className="text-lg font-medium text-base-content/90 tracking-tight">cms</h1>
          <p className="mt-1 text-[13px] text-base-content/40">sign in to your account</p>
        </div>

        {forgot ? (
          <form onSubmit={handleForgot} className="card bg-base-100 border border-base-300/40 shadow-elev-2">
            <div className="card-body p-5 gap-4">
              {error && <div className="alert alert-error text-[12px] py-2 px-3">{error}</div>}
              {forgotSent ? (
                <p className="text-[13px] text-base-content/70">If an account exists for that email, a reset link has been sent. It is valid for 60 minutes.</p>
              ) : (
                <>
                  <fieldset className="fieldset">
                    <label className="fieldset-label text-[12px] text-base-content/50 mb-1">Email</label>
                    <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus autoComplete="email" className="input input-bordered input-sm w-full text-[13px]" />
                  </fieldset>
                  <button type="submit" disabled={loading} className="btn btn-primary btn-sm w-full text-[12px]">
                    {loading && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                    {loading ? 'sending...' : 'send reset link'}
                  </button>
                </>
              )}
              <button type="button" onClick={() => { setForgot(false); setForgotSent(false); setError(''); }} className="text-[11px] text-base-content/40 hover:text-base-content/70 text-center">
                back to sign in
              </button>
            </div>
          </form>
        ) : (
        <form onSubmit={handleSubmit} className="card bg-base-100 border border-base-300/40 shadow-elev-2">
          <div className="card-body p-5 gap-4">
            {error && (
              <div className="alert alert-error text-[12px] py-2 px-3">
                {error}
              </div>
            )}

            <fieldset className="fieldset">
              <label className="fieldset-label text-[12px] text-base-content/50 mb-1">Email</label>
              <input
                type="email"
                id="login-email"
                name="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                autoFocus
                autoComplete="email"
                className="input input-bordered input-sm w-full text-[13px]"
                placeholder="admin@example.com"
              />
            </fieldset>

            <fieldset className="fieldset">
              <label className="fieldset-label text-[12px] text-base-content/50 mb-1">Password</label>
              <input
                type="password"
                id="login-password"
                name="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                autoComplete="current-password"
                className="input input-bordered input-sm w-full text-[13px]"
                placeholder="Enter your password"
              />
            </fieldset>

            <button type="submit" disabled={loading} className="btn btn-primary btn-sm w-full text-[12px] mt-1">
              {loading && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
              {loading ? 'signing in...' : 'sign in'}
            </button>
            <button type="button" onClick={() => { setForgot(true); setError(''); }} className="text-[11px] text-base-content/40 hover:text-base-content/70 text-center">
              forgot your password?
            </button>
          </div>
        </form>
        )}
      </div>
    </div>
  );
}
