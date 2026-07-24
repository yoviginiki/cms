import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import {
  ArrowLeft, Bug, ExternalLink, FileText, Loader2, Play, RefreshCw, Route as RouteIcon, Camera,
} from 'lucide-react';
import { migration } from '@/lib/api';

interface RunSummary {
  id: string;
  tool: string;
  origin: string;
  status: 'queued' | 'running' | 'done' | 'failed';
  created_at: string;
  finished_at: string | null;
  error: string | null;
  result: { summary?: Record<string, number | null> | null } | null;
}

interface RunDetail extends RunSummary {
  options: Record<string, unknown>;
  log: { t: string; line: string }[];
  result: {
    summary?: Record<string, number | null>;
    pages?: DiffPage[];
    unmapped?: string[];
    missing?: string[];
    empty?: string[];
    failed?: Record<string, string>;
    unresolved_links?: string[];
    artifacts?: string[];
  } | null;
}

interface DiffPage {
  label: string;
  text_coverage: number;
  missing_headings: string[];
  missing_images: string[];
  missing_links: string[];
  visual?: { mismatchPct: number | null; originShot: string | null; newShot: string | null; error: string | null };
  mobile?: { horizontalOverflow?: boolean; squeezedGrids?: unknown[]; sectionSeams?: unknown[]; narrowBanners?: unknown[]; edgeFlushTextBlocks?: number; error?: string };
}

const TOOLS = [
  {
    key: 'spider',
    icon: Bug,
    title: 'Spider rebuild',
    blurb: 'Rebuild imported pages and posts from the origin site’s rendered HTML — native blocks, hero, featured images, SEO meta and internal links.',
  },
  {
    key: 'redirects',
    icon: RouteIcon,
    title: 'Redirect map',
    blurb: 'Generate 301 maps (.htaccess + nginx include) from every origin URL to its migrated counterpart, so no SEO is lost at cutover.',
  },
  {
    key: 'diff',
    icon: Camera,
    title: 'Verify migration',
    blurb: 'Element-by-element diff of origin pages vs their migrated counterparts — text coverage, headings, images, links — with optional side-by-side screenshots.',
  },
] as const;

export default function MigrationPage() {
  const { siteId = '' } = useParams();
  const qc = useQueryClient();
  const [origin, setOrigin] = useState('');
  const [tool, setTool] = useState<'spider' | 'redirects' | 'diff'>('diff');
  const [activeRun, setActiveRun] = useState<string | null>(null);

  // options
  const [dry, setDry] = useState(true);
  const [skip, setSkip] = useState('');
  const [deploy, setDeploy] = useState(false);
  const [limit, setLimit] = useState(0);
  const [includeHome, setIncludeHome] = useState(true);
  const [screenshots, setScreenshots] = useState(true);
  const [mobile, setMobile] = useState(true);

  const runsQuery = useQuery({
    queryKey: ['migration-runs', siteId],
    queryFn: async () => (await migration.runs(siteId)).data.data as RunSummary[],
  });

  const runQuery = useQuery({
    queryKey: ['migration-run', siteId, activeRun],
    queryFn: async () => (await migration.run(siteId, activeRun as string)).data.data as RunDetail,
    enabled: activeRun !== null,
    refetchInterval: (q) => {
      const status = (q.state.data as RunDetail | undefined)?.status;
      return status === 'queued' || status === 'running' ? 2500 : false;
    },
  });

  useEffect(() => {
    if (runQuery.data && ['done', 'failed'].includes(runQuery.data.status)) {
      qc.invalidateQueries({ queryKey: ['migration-runs', siteId] });
    }
  }, [runQuery.data?.status]); // eslint-disable-line react-hooks/exhaustive-deps

  const startMutation = useMutation({
    mutationFn: async () => {
      const options: Record<string, unknown> =
        tool === 'spider'
          ? { dry, skip: skip.split(',').map((s) => s.trim()).filter(Boolean) }
          : tool === 'redirects'
            ? { deploy }
            : { limit, include_home: includeHome, screenshots, mobile };
      return (await migration.start(siteId, tool, origin, options)).data.data as RunDetail;
    },
    onSuccess: (run) => {
      setActiveRun(run.id);
      qc.invalidateQueries({ queryKey: ['migration-runs', siteId] });
    },
  });

  const run = runQuery.data;
  const originValid = useMemo(() => /^https?:\/\/[^\s]+\.[^\s]+/.test(origin), [origin]);

  return (
    <div className="max-w-5xl mx-auto px-6 py-8">
      <Link to={`/sites/${siteId}/pages`} className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800">
        <ArrowLeft className="w-4 h-4" /> Back to site
      </Link>

      <div className="mt-4 mb-8">
        <h1 className="text-2xl font-semibold text-gray-900">Migration tools</h1>
        <p className="text-sm text-gray-500 mt-1">
          Rebuild content from a live origin, generate cutover redirects, and verify the migrated site page-by-page.
        </p>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl p-5 mb-8">
        <label className="block text-sm font-medium text-gray-700 mb-1.5">Origin site URL</label>
        <input
          type="url"
          value={origin}
          onChange={(e) => setOrigin(e.target.value)}
          placeholder="https://old-site.example.com"
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/10"
        />

        <div className="grid sm:grid-cols-3 gap-3 mt-5">
          {TOOLS.map((t) => (
            <button
              key={t.key}
              type="button"
              onClick={() => setTool(t.key)}
              className={`text-left border rounded-xl p-4 transition ${tool === t.key ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-400'}`}
            >
              <t.icon className="w-5 h-5 mb-2 text-gray-700" />
              <div className="font-medium text-sm text-gray-900">{t.title}</div>
              <div className="text-xs text-gray-500 mt-1 leading-relaxed">{t.blurb}</div>
            </button>
          ))}
        </div>

        <div className="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-700">
          {tool === 'spider' && (
            <>
              <label className="inline-flex items-center gap-2">
                <input type="checkbox" checked={dry} onChange={(e) => setDry(e.target.checked)} />
                Dry run (extract, write nothing)
              </label>
              <label className="inline-flex items-center gap-2">
                Skip slugs
                <input
                  type="text"
                  value={skip}
                  onChange={(e) => setSkip(e.target.value)}
                  placeholder="home, contact"
                  className="border border-gray-300 rounded px-2 py-1 text-xs w-44"
                />
              </label>
            </>
          )}
          {tool === 'redirects' && (
            <label className="inline-flex items-center gap-2">
              <input type="checkbox" checked={deploy} onChange={(e) => setDeploy(e.target.checked)} />
              Also deploy .htaccess into the live docroot
            </label>
          )}
          {tool === 'diff' && (
            <>
              <label className="inline-flex items-center gap-2">
                <input type="checkbox" checked={includeHome} onChange={(e) => setIncludeHome(e.target.checked)} />
                Include homepages
              </label>
              <label className="inline-flex items-center gap-2">
                <input type="checkbox" checked={screenshots} onChange={(e) => setScreenshots(e.target.checked)} />
                Screenshots + visual mismatch score
              </label>
              <label className="inline-flex items-center gap-2">
                <input type="checkbox" checked={mobile} onChange={(e) => setMobile(e.target.checked)} />
                Mobile audit (390px)
              </label>
              <label className="inline-flex items-center gap-2">
                Page limit
                <input
                  type="number"
                  min={0}
                  max={500}
                  value={limit}
                  onChange={(e) => setLimit(Number(e.target.value))}
                  className="border border-gray-300 rounded px-2 py-1 text-xs w-16"
                  title="0 = all pages"
                />
              </label>
            </>
          )}
        </div>

        <button
          type="button"
          disabled={!originValid || startMutation.isPending}
          onClick={() => startMutation.mutate()}
          className="mt-5 inline-flex items-center gap-2 bg-gray-900 text-white text-sm font-medium rounded-lg px-4 py-2 disabled:opacity-40"
        >
          {startMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Play className="w-4 h-4" />}
          Run {TOOLS.find((t) => t.key === tool)?.title.toLowerCase()}
        </button>
        {startMutation.isError && (
          <p className="text-sm text-red-600 mt-2">
            {(startMutation.error as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Could not start the run.'}
          </p>
        )}
      </div>

      {run && (
        <div className="bg-white border border-gray-200 rounded-xl p-5 mb-8">
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-medium text-gray-900 flex items-center gap-2">
              {['queued', 'running'].includes(run.status) && <Loader2 className="w-4 h-4 animate-spin text-gray-500" />}
              {run.tool} — {run.status}
            </h2>
            <span className="text-xs text-gray-400">{run.id.slice(0, 8)}</span>
          </div>

          {run.error && <p className="text-sm text-red-600 mb-3">{run.error}</p>}

          {run.result?.summary && (
            <div className="flex flex-wrap gap-2 mb-4">
              {Object.entries(run.result.summary).map(([k, v]) => (
                <span key={k} className="text-xs bg-gray-100 rounded-full px-3 py-1 text-gray-700">
                  {k.replace(/_/g, ' ')}: <strong>{v === null ? '—' : v}</strong>
                </span>
              ))}
            </div>
          )}

          {run.tool === 'redirects' && run.status === 'done' && (
            <div className="flex flex-wrap gap-3 mb-4 text-sm">
              {(run.result?.artifacts || []).map((a) => (
                <a key={a} href={migration.artifactUrl(siteId, a)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-blue-700 hover:underline">
                  <FileText className="w-4 h-4" /> {a}
                </a>
              ))}
            </div>
          )}

          {run.tool === 'diff' && (run.result?.pages?.length ?? 0) > 0 && (
            <div className="overflow-x-auto mb-4">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-200">
                    <th className="py-2 pr-3">Page</th>
                    <th className="py-2 pr-3">Text</th>
                    <th className="py-2 pr-3">Visual</th>
                    <th className="py-2 pr-3">Mobile</th>
                    <th className="py-2 pr-3">Missing</th>
                    <th className="py-2">Shots</th>
                  </tr>
                </thead>
                <tbody>
                  {run.result!.pages!.map((p) => (
                    <tr key={p.label} className="border-b border-gray-100 align-top">
                      <td className="py-2 pr-3 font-mono text-xs">{p.label}</td>
                      <td className={`py-2 pr-3 ${p.text_coverage < 95 ? 'text-amber-600 font-medium' : 'text-gray-700'}`}>{p.text_coverage}%</td>
                      <td className="py-2 pr-3">
                        {p.visual?.mismatchPct != null
                          ? <span className={p.visual.mismatchPct > 15 ? 'text-amber-600 font-medium' : 'text-gray-700'}>{p.visual.mismatchPct}%</span>
                          : <span className="text-gray-300">—</span>}
                      </td>
                      <td className="py-2 pr-3 text-xs">
                        {p.mobile ? (() => {
                          const issues = [
                            p.mobile.horizontalOverflow ? 'overflow' : null,
                            (p.mobile.squeezedGrids?.length ?? 0) > 0 ? `grids:${p.mobile.squeezedGrids!.length}` : null,
                            (p.mobile.sectionSeams?.length ?? 0) > 0 ? `seams:${p.mobile.sectionSeams!.length}` : null,
                            (p.mobile.narrowBanners?.length ?? 0) > 0 ? `banners:${p.mobile.narrowBanners!.length}` : null,
                            (p.mobile.edgeFlushTextBlocks ?? 0) > 3 ? 'edge-flush' : null,
                          ].filter(Boolean);
                          if (p.mobile.error) return <span className="text-gray-400">{p.mobile.error}</span>;
                          return issues.length
                            ? <span className="text-amber-600 font-medium">{issues.join(', ')}</span>
                            : <span className="text-green-700">clean</span>;
                        })() : <span className="text-gray-300">—</span>}
                      </td>
                      <td className="py-2 pr-3 text-xs text-gray-500">
                        {p.missing_headings.length > 0 && <div>headings: {p.missing_headings.length}</div>}
                        {p.missing_images.length > 0 && <div>images: {p.missing_images.length}</div>}
                        {p.missing_links.length > 0 && <div>links: {p.missing_links.length}</div>}
                        {p.missing_headings.length + p.missing_images.length + p.missing_links.length === 0 && '—'}
                      </td>
                      <td className="py-2 text-xs">
                        {p.visual?.originShot && (
                          <>
                            <a className="text-blue-700 hover:underline inline-flex items-center gap-1" href={migration.artifactUrl(siteId, p.visual.originShot)} target="_blank" rel="noreferrer">origin <ExternalLink className="w-3 h-3" /></a>
                            {' · '}
                            <a className="text-blue-700 hover:underline inline-flex items-center gap-1" href={migration.artifactUrl(siteId, p.visual.newShot || '')} target="_blank" rel="noreferrer">new <ExternalLink className="w-3 h-3" /></a>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {(run.result?.unmapped?.length ?? 0) > 0 && (
            <details className="text-sm text-gray-600 mb-2">
              <summary className="cursor-pointer">Unmapped origin URLs ({run.result!.unmapped!.length})</summary>
              <ul className="mt-1 list-disc pl-5 text-xs">{run.result!.unmapped!.map((u) => <li key={u}>{u}</li>)}</ul>
            </details>
          )}

          {run.log.length > 0 && (
            <pre className="bg-gray-950 text-gray-200 text-xs rounded-lg p-3 max-h-64 overflow-y-auto">
              {run.log.map((l) => `${l.t}  ${l.line}`).join('\n')}
            </pre>
          )}
        </div>
      )}

      <div className="bg-white border border-gray-200 rounded-xl p-5">
        <div className="flex items-center justify-between mb-3">
          <h2 className="font-medium text-gray-900">Recent runs</h2>
          <button type="button" onClick={() => runsQuery.refetch()} className="text-gray-400 hover:text-gray-700">
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
        {(runsQuery.data?.length ?? 0) === 0 && <p className="text-sm text-gray-400">No runs yet.</p>}
        <ul className="divide-y divide-gray-100">
          {runsQuery.data?.map((r) => (
            <li key={r.id}>
              <button
                type="button"
                onClick={() => setActiveRun(r.id)}
                className={`w-full text-left py-2.5 flex items-center justify-between gap-3 hover:bg-gray-50 rounded px-2 -mx-2 ${activeRun === r.id ? 'bg-gray-50' : ''}`}
              >
                <span className="text-sm text-gray-800">
                  <span className="font-medium">{r.tool}</span>
                  <span className="text-gray-400"> · {r.origin.replace(/^https?:\/\//, '')}</span>
                </span>
                <span className={`text-xs rounded-full px-2 py-0.5 ${
                  r.status === 'done' ? 'bg-green-100 text-green-700'
                    : r.status === 'failed' ? 'bg-red-100 text-red-700'
                      : 'bg-amber-100 text-amber-700'
                }`}>{r.status}</span>
              </button>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
