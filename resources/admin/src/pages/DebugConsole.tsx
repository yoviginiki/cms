import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { RefreshCw, Trash2, Loader2, AlertTriangle, CheckCircle, XCircle, Server, Database, HardDrive, Activity, Zap, AlertOctagon, ChevronDown, ChevronRight } from 'lucide-react';
import { api } from '@/lib/api';

type Tab = 'health' | 'content' | 'logs' | 'queue' | 'deploy' | 'config';

interface HealthData {
  health: {
    database: { ok: boolean; latency_ms: number; driver: string };
    redis: { ok: boolean; latency_ms: number; memory: string | null; keys: number; enabled: boolean };
    queue: { worker_active: boolean; pending: number; failed: number; oldest_pending: string | null; last_job: string | null; driver: string };
    disk: { free: string | null; total: string | null };
  };
  system: {
    php_version: string; laravel_version: string; cms_version: string; app_env: string;
    debug_mode: boolean; open_basedir: string; memory_limit: string; max_execution_time: string;
    upload_max_filesize: string; post_max_size: string; timezone: string;
    missing_extensions: string[]; config_issues: string[];
  };
  site: {
    name: string; domain: string; pages: number; published_pages: number; posts: number;
    published_posts: number; categories: number; tags: number; assets: number; blocks: number;
    grids: number; menus: number; menu_items: number; active_theme: string; auto_publish: boolean;
    homepage_id: string | null;
  } | null;
  deploy: { total: number; live: number; failed: number; last_status: string; last_at: string; last_duration: string | null; last_error: string | null } | null;
  failed_jobs: Array<{ id: string; job: string; error: string; failed_at: string }>;
  content_issues: string[];
  recent_errors: Array<{ time: string; message: string }>;
  storage: { assets: string; builds: string; imports: string; logs: string };
}

interface LogEntry { timestamp: string; channel: string; level: string; message: string; trace: string }

export default function DebugConsole() {
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<Tab>('health');
  const [logLevel, setLogLevel] = useState('all');
  const [expandedError, setExpandedError] = useState<number | null>(null);

  const { data, isLoading, refetch } = useQuery<HealthData>({
    queryKey: ['debug'],
    queryFn: () => api.get('/debug').then(r => r.data.data),
    refetchInterval: 15000,
  });

  const { data: logData } = useQuery<{ entries: LogEntry[]; stats: Record<string, number> }>({
    queryKey: ['debug-logs', logLevel],
    queryFn: () => api.get(`/debug/logs?lines=200&level=${logLevel}`).then(r => r.data.data),
    enabled: tab === 'logs',
    refetchInterval: 5000,
  });

  const clearLogsMut = useMutation({ mutationFn: () => api.delete('/debug/logs'), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['debug-logs'] }) });
  const retryMut = useMutation({ mutationFn: () => api.post('/debug/retry-failed'), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['debug'] }) });
  const flushMut = useMutation({ mutationFn: () => api.post('/debug/flush-failed'), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['debug'] }) });
  const clearCacheMut = useMutation({ mutationFn: (type: string) => api.post('/debug/clear-cache', { type }), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['debug'] }) });

  if (isLoading || !data) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  const d = data;
  const tabs: { key: Tab; label: string; badge?: number }[] = [
    { key: 'health', label: 'Health' },
    { key: 'content', label: 'Content', badge: d.content_issues.length || undefined },
    { key: 'logs', label: 'Logs', badge: d.recent_errors.length || undefined },
    { key: 'queue', label: 'Queue', badge: d.health.queue.failed || undefined },
    { key: 'deploy', label: 'Deploys' },
    { key: 'config', label: 'Config', badge: d.system.config_issues.length || undefined },
  ];

  return (
    <div className="max-w-6xl mx-auto py-6 px-4">
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-2xl font-bold text-gray-900">Debug Console</h1>
        <button onClick={() => refetch()} className="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50">
          <RefreshCw className="h-3.5 w-3.5" /> Refresh
        </button>
      </div>

      {/* Health bar — always visible */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-2 mb-6">
        <Pill ok={d.health.database.ok} label="Database" value={`${d.health.database.latency_ms}ms`} />
        <Pill ok={d.health.redis.ok} label="Redis" value={d.health.redis.enabled ? `${d.health.redis.latency_ms}ms` : 'OFF'} />
        <Pill ok={d.health.queue.worker_active} label="Worker" value={d.health.queue.worker_active ? 'Running' : 'Stopped'} />
        <Pill ok={d.health.queue.failed === 0} label="Failed Jobs" value={String(d.health.queue.failed)} />
        <Pill ok={d.deploy?.last_status === 'live'} label="Last Deploy" value={d.deploy?.last_status || 'none'} />
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="flex gap-1">
          {tabs.map(t => (
            <button key={t.key} onClick={() => setTab(t.key)}
              className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors relative ${
                tab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}>
              {t.label}
              {t.badge && t.badge > 0 && (
                <span className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">{t.badge}</span>
              )}
            </button>
          ))}
        </nav>
      </div>

      {/* HEALTH TAB */}
      {tab === 'health' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card title="Database" icon={Database} ok={d.health.database.ok}>
              <KV k="Driver" v={d.health.database.driver} />
              <KV k="Latency" v={`${d.health.database.latency_ms}ms`} />
              <KV k="Status" v={d.health.database.ok ? 'Connected' : 'FAILED'} color={d.health.database.ok ? 'green' : 'red'} />
            </Card>
            <Card title="Redis" icon={Zap} ok={d.health.redis.ok}>
              <KV k="Enabled" v={d.health.redis.enabled ? 'Yes' : 'No'} />
              <KV k="Latency" v={d.health.redis.enabled ? `${d.health.redis.latency_ms}ms` : '—'} />
              <KV k="Memory" v={d.health.redis.memory || '—'} />
              <KV k="Keys" v={String(d.health.redis.keys)} />
            </Card>
            <Card title="Disk" icon={HardDrive} ok>
              <KV k="Free" v={d.health.disk.free || '?'} />
              <KV k="Total" v={d.health.disk.total || '?'} />
              <KV k="Assets" v={d.storage.assets} />
              <KV k="Builds" v={d.storage.builds} />
              <KV k="Logs" v={d.storage.logs} />
            </Card>
          </div>
          <Card title="PHP Environment" icon={Server} ok>
            <div className="grid grid-cols-2 gap-x-6 gap-y-1">
              <KV k="PHP" v={d.system.php_version} />
              <KV k="Laravel" v={d.system.laravel_version} />
              <KV k="Memory Limit" v={d.system.memory_limit} />
              <KV k="Max Execution" v={d.system.max_execution_time} />
              <KV k="Upload Max" v={d.system.upload_max_filesize} />
              <KV k="Post Max" v={d.system.post_max_size} />
              <KV k="Timezone" v={d.system.timezone} />
              <KV k="open_basedir" v={d.system.open_basedir.length > 40 ? d.system.open_basedir.slice(0, 40) + '...' : d.system.open_basedir} />
            </div>
            {d.system.missing_extensions.length > 0 && (
              <div className="mt-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                Missing extensions: {d.system.missing_extensions.join(', ')}
              </div>
            )}
          </Card>
        </div>
      )}

      {/* CONTENT TAB */}
      {tab === 'content' && d.site && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {[
              { label: 'Pages', total: d.site.pages, published: d.site.published_pages },
              { label: 'Posts', total: d.site.posts, published: d.site.published_posts },
              { label: 'Categories', total: d.site.categories },
              { label: 'Tags', total: d.site.tags },
              { label: 'Assets', total: d.site.assets },
              { label: 'Blocks', total: d.site.blocks },
              { label: 'Grids', total: d.site.grids },
              { label: 'Menu Items', total: d.site.menu_items },
            ].map(s => (
              <div key={s.label} className="bg-white rounded-lg border p-3 text-center">
                <p className="text-2xl font-bold text-gray-900">{s.total}</p>
                <p className="text-xs text-gray-500">{s.label}</p>
                {s.published !== undefined && <p className="text-[10px] text-green-600">{s.published} published</p>}
              </div>
            ))}
          </div>
          <div className="bg-white rounded-xl border p-4">
            <h3 className="font-semibold text-gray-900 mb-2">Site Info</h3>
            <div className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
              <KV k="Name" v={d.site.name} />
              <KV k="Domain" v={d.site.domain || '—'} />
              <KV k="Theme" v={d.site.active_theme} />
              <KV k="Auto-publish" v={d.site.auto_publish ? 'ON' : 'OFF'} color={d.site.auto_publish ? 'green' : 'gray'} />
              <KV k="Homepage" v={d.site.homepage_id ? 'Set' : 'Auto (slug=home)'} />
            </div>
          </div>
          {d.content_issues.length > 0 && (
            <div className="bg-white rounded-xl border border-yellow-200 p-4">
              <h3 className="font-semibold text-yellow-700 mb-2 flex items-center gap-2"><AlertTriangle className="h-4 w-4" /> Content Issues ({d.content_issues.length})</h3>
              <ul className="space-y-1">
                {d.content_issues.map((issue, i) => (
                  <li key={i} className="text-sm text-yellow-700 flex items-start gap-2">
                    <span className="text-yellow-400 mt-1">•</span> {issue}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {/* LOGS TAB */}
      {tab === 'logs' && (
        <div>
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-500">Filter:</span>
              {['all', 'error', 'warning', 'info', 'debug'].map(l => (
                <button key={l} onClick={() => setLogLevel(l)}
                  className={`px-2 py-0.5 text-xs rounded-full capitalize ${logLevel === l ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                  {l} {logData?.stats && l !== 'all' && <span className="ml-0.5 opacity-60">({(logData.stats as Record<string,number>)[l] || 0})</span>}
                </button>
              ))}
            </div>
            <button onClick={() => clearLogsMut.mutate()} className="text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
              <Trash2 className="h-3 w-3" /> Clear
            </button>
          </div>
          <div className="bg-gray-900 rounded-xl p-3 font-mono text-xs max-h-[65vh] overflow-y-auto space-y-0.5">
            {logData?.entries?.map((e, i) => (
              <div key={i}>
                <div className="flex gap-2 cursor-pointer hover:bg-gray-800 rounded px-1" onClick={() => setExpandedError(expandedError === i ? null : i)}>
                  <span className="text-gray-500 shrink-0 w-36">{e.timestamp}</span>
                  <span className={`shrink-0 w-14 text-center rounded text-[10px] font-bold uppercase ${
                    e.level === 'error' ? 'bg-red-900 text-red-300' : e.level === 'warning' ? 'bg-yellow-900 text-yellow-300' : e.level === 'info' ? 'bg-blue-900 text-blue-300' : 'bg-gray-700 text-gray-400'
                  }`}>{e.level}</span>
                  <span className="text-gray-300 truncate">{e.message.split('\n')[0].slice(0, 200)}</span>
                  {e.trace && (expandedError === i ? <ChevronDown className="h-3 w-3 text-gray-500 shrink-0 mt-0.5" /> : <ChevronRight className="h-3 w-3 text-gray-500 shrink-0 mt-0.5" />)}
                </div>
                {expandedError === i && e.trace && (
                  <pre className="text-gray-500 text-[10px] pl-52 mt-1 mb-2 max-h-40 overflow-y-auto whitespace-pre-wrap">{e.trace.slice(0, 2000)}</pre>
                )}
              </div>
            ))}
            {(!logData?.entries || logData.entries.length === 0) && <p className="text-gray-500 text-center py-4">No log entries</p>}
          </div>
        </div>
      )}

      {/* QUEUE TAB */}
      {tab === 'queue' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card title="Worker" icon={Activity} ok={d.health.queue.worker_active}>
              <KV k="Status" v={d.health.queue.worker_active ? 'Running' : 'STOPPED'} color={d.health.queue.worker_active ? 'green' : 'red'} />
              <KV k="Driver" v={d.health.queue.driver} />
              <KV k="Last Activity" v={d.health.queue.last_job ? d.health.queue.last_job.slice(0, 60) : 'none'} />
            </Card>
            <Card title="Pending" icon={Loader2} ok={d.health.queue.pending === 0}>
              <KV k="Jobs" v={String(d.health.queue.pending)} />
              <KV k="Oldest" v={d.health.queue.oldest_pending || 'none'} />
            </Card>
            <Card title="Failed" icon={AlertOctagon} ok={d.health.queue.failed === 0}>
              <KV k="Total" v={String(d.health.queue.failed)} color={d.health.queue.failed > 0 ? 'red' : undefined} />
              {d.health.queue.failed > 0 && (
                <div className="flex gap-2 mt-2">
                  <button onClick={() => retryMut.mutate()} disabled={retryMut.isPending} className="px-2 py-1 text-xs bg-orange-600 text-white rounded hover:bg-orange-700 disabled:opacity-50">Retry All</button>
                  <button onClick={() => flushMut.mutate()} disabled={flushMut.isPending} className="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50">Flush All</button>
                </div>
              )}
            </Card>
          </div>
          {d.failed_jobs.length > 0 && (
            <div className="bg-white rounded-xl border p-4">
              <h3 className="font-semibold text-gray-900 mb-3">Recent Failed Jobs</h3>
              <div className="space-y-2">
                {d.failed_jobs.map(j => (
                  <div key={j.id} className="p-3 bg-red-50 border border-red-100 rounded-lg">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-red-800">{j.job}</span>
                      <span className="text-xs text-red-400">{new Date(j.failed_at).toLocaleString()}</span>
                    </div>
                    <p className="text-xs text-red-600 font-mono">{j.error}</p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* DEPLOY TAB */}
      {tab === 'deploy' && d.deploy && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div className="bg-white rounded-lg border p-3 text-center">
              <p className="text-2xl font-bold text-gray-900">{d.deploy.total}</p><p className="text-xs text-gray-500">Total Deploys</p>
            </div>
            <div className="bg-white rounded-lg border p-3 text-center">
              <p className="text-2xl font-bold text-green-600">{d.deploy.live}</p><p className="text-xs text-gray-500">Successful</p>
            </div>
            <div className="bg-white rounded-lg border p-3 text-center">
              <p className="text-2xl font-bold text-red-600">{d.deploy.failed}</p><p className="text-xs text-gray-500">Failed</p>
            </div>
            <div className="bg-white rounded-lg border p-3 text-center">
              <p className="text-2xl font-bold text-gray-900">{d.deploy.last_duration || '—'}</p><p className="text-xs text-gray-500">Last Duration</p>
            </div>
          </div>
          <div className="bg-white rounded-xl border p-4">
            <h3 className="font-semibold text-gray-900 mb-2">Last Deployment</h3>
            <KV k="Status" v={d.deploy.last_status} color={d.deploy.last_status === 'live' ? 'green' : d.deploy.last_status === 'failed' ? 'red' : undefined} />
            <KV k="Time" v={d.deploy.last_at ? new Date(d.deploy.last_at).toLocaleString() : '—'} />
            <KV k="Duration" v={d.deploy.last_duration || '—'} />
            {d.deploy.last_error && (
              <div className="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700 font-mono whitespace-pre-wrap">{d.deploy.last_error}</div>
            )}
          </div>
        </div>
      )}

      {/* CONFIG TAB */}
      {tab === 'config' && (
        <div className="space-y-4">
          {d.system.config_issues.length > 0 && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4">
              <h3 className="font-semibold text-red-700 mb-2 flex items-center gap-2"><AlertOctagon className="h-4 w-4" /> Configuration Issues</h3>
              <ul className="space-y-1">
                {d.system.config_issues.map((issue, i) => (
                  <li key={i} className="text-sm text-red-700">• {issue}</li>
                ))}
              </ul>
            </div>
          )}
          <div className="bg-white rounded-xl border p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="font-semibold text-gray-900">Cache Management</h3>
            </div>
            <div className="flex flex-wrap gap-2">
              <button onClick={() => clearCacheMut.mutate('config')} disabled={clearCacheMut.isPending}
                className="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50 disabled:opacity-50">Clear Config Cache</button>
              <button onClick={() => clearCacheMut.mutate('routes')} disabled={clearCacheMut.isPending}
                className="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50 disabled:opacity-50">Clear Route Cache</button>
              <button onClick={() => clearCacheMut.mutate('views')} disabled={clearCacheMut.isPending}
                className="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50 disabled:opacity-50">Clear View Cache</button>
              <button onClick={() => clearCacheMut.mutate('cache')} disabled={clearCacheMut.isPending}
                className="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50 disabled:opacity-50">Clear App Cache</button>
              <button onClick={() => clearCacheMut.mutate('all')} disabled={clearCacheMut.isPending}
                className="px-3 py-1.5 text-sm bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-50">Clear All</button>
            </div>
            {clearCacheMut.isSuccess && <p className="mt-2 text-xs text-green-600">Cache cleared successfully</p>}
          </div>
          <div className="bg-white rounded-xl border p-4">
            <h3 className="font-semibold text-gray-900 mb-2">Environment</h3>
            <div className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
              <KV k="APP_ENV" v={d.system.app_env} color={d.system.app_env === 'production' ? 'green' : 'yellow'} />
              <KV k="APP_DEBUG" v={d.system.debug_mode ? 'ON' : 'OFF'} color={d.system.debug_mode ? 'red' : 'green'} />
              <KV k="CMS Version" v={d.system.cms_version} />
              <KV k="Timezone" v={d.system.timezone} />
              <KV k="open_basedir" v={d.system.open_basedir.length > 50 ? 'Restricted' : d.system.open_basedir} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Pill({ ok, label, value }: { ok: boolean; label: string; value: string }) {
  return (
    <div className={`flex items-center gap-2 px-3 py-2 rounded-lg border ${ok ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}`}>
      {ok ? <CheckCircle className="h-4 w-4 text-green-500 shrink-0" /> : <XCircle className="h-4 w-4 text-red-500 shrink-0" />}
      <div className="min-w-0">
        <p className="text-[10px] text-gray-500 uppercase">{label}</p>
        <p className={`text-sm font-bold ${ok ? 'text-green-800' : 'text-red-800'}`}>{value}</p>
      </div>
    </div>
  );
}

function Card({ title, icon: Icon, ok, children }: { title: string; icon: React.ElementType; ok: boolean; children: React.ReactNode }) {
  return (
    <div className="bg-white rounded-xl border p-4">
      <div className="flex items-center gap-2 mb-3">
        <Icon className={`h-4 w-4 ${ok ? 'text-green-500' : 'text-red-500'}`} />
        <h3 className="font-semibold text-gray-900 text-sm">{title}</h3>
        {ok ? <CheckCircle className="h-3 w-3 text-green-400 ml-auto" /> : <XCircle className="h-3 w-3 text-red-400 ml-auto" />}
      </div>
      <div className="space-y-1">{children}</div>
    </div>
  );
}

function KV({ k, v, color }: { k: string; v: string; color?: string }) {
  const colorClass = color === 'green' ? 'text-green-600' : color === 'red' ? 'text-red-600' : color === 'yellow' ? 'text-yellow-600' : 'text-gray-900';
  return (
    <div className="flex justify-between text-sm">
      <span className="text-gray-500">{k}</span>
      <span className={`font-medium ${colorClass}`}>{v}</span>
    </div>
  );
}
