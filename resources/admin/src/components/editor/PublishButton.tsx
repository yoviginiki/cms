import { useState, useRef, useEffect } from 'react';
import { Loader2, CheckCircle, AlertCircle, Upload, ChevronDown, Clock, RotateCcw, Eye, Download, RefreshCw } from 'lucide-react';
import { api } from '@/lib/api';
import { useDeploymentStatus } from '@/hooks/useDeploymentStatus';
import { useQuery } from '@tanstack/react-query';

interface PublishButtonProps {
  siteId: string;
  publicBase?: string;
}

interface Deployment {
  id: string;
  status: string;
  type: string;
  metadata: Record<string, any>;
  started_at: string | null;
  completed_at: string | null;
  error_log: string | null;
  created_at: string;
}

export function PublishButton({ siteId, publicBase }: PublishButtonProps) {
  const [deploymentId, setDeploymentId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const { deployment, isPublishing, progress } = useDeploymentStatus(siteId, deploymentId);

  // Deployment history
  const { data: historyData, refetch: refetchHistory } = useQuery({
    queryKey: ['deployments', siteId],
    queryFn: () => api.get(`/sites/${siteId}/deployments?per_page=5`).then(r => r.data.data as Deployment[]),
    enabled: menuOpen,
    refetchOnWindowFocus: false,
  });

  // Close menu on outside click
  useEffect(() => {
    if (!menuOpen) return;
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [menuOpen]);

  async function handlePublish(type: 'full' | 'partial' = 'full') {
    setError(null);
    setMenuOpen(false);
    try {
      const res = await api.post(`/sites/${siteId}/publish`, { type });
      setDeploymentId(res.data.data.id);
      setTimeout(() => refetchHistory(), 3000);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Publish failed';
      setError(msg);
    }
  }

  async function handleRollback(depId: string) {
    setError(null);
    setMenuOpen(false);
    try {
      const res = await api.post(`/sites/${siteId}/deployments/${depId}/rollback`);
      setDeploymentId(res.data.data.id);
      refetchHistory();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Rollback failed';
      setError(msg);
    }
  }

  const formatTime = (ts: string | null) => {
    if (!ts) return '';
    const d = new Date(ts);
    const now = new Date();
    const diff = now.getTime() - d.getTime();
    if (diff < 60000) return 'just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  };

  const statusColor = (s: string) => {
    if (s === 'live') return 'text-success';
    if (s === 'failed') return 'text-error';
    if (s === 'building' || s === 'deploying' || s === 'queued') return 'text-warning';
    return 'text-base-content/40';
  };

  // Success state — auto-dismiss after 5s
  useEffect(() => {
    if (deployment?.status === 'live') {
      const t = setTimeout(() => setDeploymentId(null), 5000);
      return () => clearTimeout(t);
    }
  }, [deployment?.status]);

  // Publishing in progress
  if (isPublishing) {
    const pct = progress.pages_total
      ? Math.round((progress.pages_built! / progress.pages_total) * 100)
      : 0;
    return (
      <button disabled className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium opacity-80">
        <Loader2 size={14} className="animate-spin" />
        {progress.current_step === 'building'
          ? `Building ${progress.pages_built}/${progress.pages_total} (${pct}%)`
          : progress.current_step === 'deploying' ? 'Deploying...' : 'Queued...'}
      </button>
    );
  }

  return (
    <div className="relative" ref={menuRef}>
      <div className="flex items-center">
        {/* Main publish button */}
        <button
          onClick={() => handlePublish('full')}
          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-l-md text-sm font-medium ${
            deployment?.status === 'live' ? 'bg-green-600 text-white'
            : error ? 'bg-red-600 text-white hover:bg-red-700'
            : 'bg-emerald-600 text-white hover:bg-emerald-700'
          }`}
        >
          {deployment?.status === 'live' ? <CheckCircle size={14} /> : error ? <AlertCircle size={14} /> : <Upload size={14} />}
          {deployment?.status === 'live' ? 'Published!' : error ? 'Retry' : 'Publish'}
        </button>

        {/* Dropdown toggle */}
        <button
          onClick={() => setMenuOpen(!menuOpen)}
          className={`px-1.5 py-1.5 rounded-r-md border-l border-white/20 text-sm ${
            deployment?.status === 'live' ? 'bg-green-600 text-white'
            : error ? 'bg-red-600 text-white'
            : 'bg-emerald-600 text-white hover:bg-emerald-700'
          }`}
        >
          <ChevronDown size={14} />
        </button>
      </div>

      {error && <div className="absolute top-full right-0 mt-1 text-xs text-error bg-error/10 px-2 py-1 rounded whitespace-nowrap z-50">{error}</div>}

      {/* Dropdown menu */}
      {menuOpen && (
        <div className="absolute top-full right-0 mt-1 w-72 bg-base-100 border border-base-300/30 rounded-lg shadow-xl z-50 overflow-hidden">
          {/* Actions */}
          <div className="p-2 border-b border-base-300/20 space-y-1">
            <button onClick={() => handlePublish('full')} className="w-full flex items-center gap-2 px-2 py-1.5 rounded text-[11px] hover:bg-base-200 text-left">
              <Upload size={12} className="text-emerald-500 shrink-0" /> Full Publish <span className="text-base-content/30 ml-auto">Rebuild all</span>
            </button>
            <button onClick={() => handlePublish('partial')} className="w-full flex items-center gap-2 px-2 py-1.5 rounded text-[11px] hover:bg-base-200 text-left">
              <RefreshCw size={12} className="text-blue-500 shrink-0" /> Quick Publish <span className="text-base-content/30 ml-auto">Changed only</span>
            </button>
            {publicBase && (
              <a href={publicBase} target="_blank" rel="noopener" className="w-full flex items-center gap-2 px-2 py-1.5 rounded text-[11px] hover:bg-base-200">
                <Eye size={12} className="text-info shrink-0" /> View Live Site
              </a>
            )}
          </div>

          {/* Deployment history */}
          <div className="p-2">
            <div className="text-[9px] text-base-content/30 uppercase tracking-wider font-medium mb-1.5">Recent Deployments</div>
            {!historyData ? (
              <div className="text-[10px] text-base-content/20 text-center py-2"><Loader2 size={12} className="animate-spin inline" /> Loading...</div>
            ) : historyData.length === 0 ? (
              <div className="text-[10px] text-base-content/20 text-center py-2">No deployments yet</div>
            ) : (
              <div className="space-y-1">
                {historyData.map((dep) => (
                  <div key={dep.id} className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-base-200 text-[10px]">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-1">
                        <span className={`font-medium ${statusColor(dep.status)}`}>{dep.status}</span>
                        <span className="text-base-content/20">{dep.type}</span>
                      </div>
                      <div className="text-base-content/30 flex items-center gap-1">
                        <Clock size={9} /> {formatTime(dep.completed_at || dep.started_at || dep.created_at)}
                        {dep.metadata?.pages_total && <span>· {dep.metadata.pages_total} pages</span>}
                      </div>
                    </div>
                    {dep.status === 'live' && (
                      <button onClick={() => handleRollback(dep.id)} className="btn btn-ghost btn-xs text-[9px] gap-0.5" title="Rollback to this version">
                        <RotateCcw size={10} />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
