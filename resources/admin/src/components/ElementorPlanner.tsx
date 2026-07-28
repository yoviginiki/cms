import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Boxes, Copy, Check, Loader2 } from 'lucide-react';
import { migration } from '@/lib/api';

interface PlanPage {
  wpId: number;
  slug: string;
  title: string;
  matched: boolean;
}
interface Plan {
  pages: PlanPage[];
  postsAvailable: number;
  catalogPostId: number | null;
  command: string;
}

const input =
  'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/10';

/**
 * "Assisted" Elementor import: connect once to the source WP database
 * (credentials are never stored) to enumerate its Elementor pages and get the
 * ready-to-run elementor:import command with the id→slug mapping.
 */
export default function ElementorPlanner({ siteId }: { siteId: string }) {
  const [db, setDb] = useState('');
  const [user, setUser] = useState('');
  const [pass, setPass] = useState('');
  const [prefix, setPrefix] = useState('wp_');
  const [origin, setOrigin] = useState('');
  const [copied, setCopied] = useState(false);

  const plan = useMutation({
    mutationFn: async () =>
      (
        await migration.elementorPlan(siteId, {
          wp_db: db,
          wp_user: user,
          wp_pass: pass,
          wp_prefix: prefix || undefined,
          origin: origin || undefined,
        })
      ).data.data as Plan,
  });

  const data = plan.data;

  return (
    <div className="bg-white border border-gray-200 rounded-xl p-5 mb-8">
      <h2 className="font-medium text-gray-900 flex items-center gap-2 mb-1">
        <Boxes className="w-4 h-4" /> Elementor import planner
      </h2>
      <p className="text-sm text-gray-500 mb-4">
        Connect once to the source WordPress database (credentials are <strong>not stored</strong>) to list its
        Elementor pages and get the ready-to-run <code className="text-gray-700">elementor:import</code> command. The
        host is locked to localhost.
      </p>

      <div className="grid sm:grid-cols-2 gap-3">
        <input className={input} placeholder="WP database name" value={db} onChange={(e) => setDb(e.target.value)} />
        <input className={input} placeholder="WP database user" value={user} onChange={(e) => setUser(e.target.value)} />
        <input
          className={input}
          type="password"
          placeholder="WP database password"
          value={pass}
          onChange={(e) => setPass(e.target.value)}
        />
        <input className={input} placeholder="Table prefix (wp_)" value={prefix} onChange={(e) => setPrefix(e.target.value)} />
        <input
          className={`${input} sm:col-span-2`}
          placeholder="Origin URL (optional, for media by path) — https://source.example.com"
          value={origin}
          onChange={(e) => setOrigin(e.target.value)}
        />
      </div>

      <button
        type="button"
        onClick={() => plan.mutate()}
        disabled={!db || !user || !pass || plan.isPending}
        className="mt-4 inline-flex items-center gap-2 bg-gray-900 text-white text-sm font-medium rounded-lg px-4 py-2 disabled:opacity-40"
      >
        {plan.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
        Enumerate pages
      </button>

      {plan.isError && (
        <p className="text-sm text-red-600 mt-3">
          {(plan.error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
            'Could not read the WordPress database.'}
        </p>
      )}

      {data && (
        <div className="mt-5 space-y-4">
          <div className="overflow-x-auto border border-gray-200 rounded-lg">
            <table className="w-full text-xs">
              <thead className="bg-gray-50 text-gray-500">
                <tr>
                  <th className="text-left font-medium px-3 py-2">WP id</th>
                  <th className="text-left font-medium px-3 py-2">slug</th>
                  <th className="text-left font-medium px-3 py-2">title</th>
                  <th className="text-left font-medium px-3 py-2">in this site</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data.pages.map((p) => (
                  <tr key={p.wpId} className={p.matched ? 'bg-green-50/50' : ''}>
                    <td className="px-3 py-1.5 text-gray-500">{p.wpId}</td>
                    <td className="px-3 py-1.5 font-mono text-gray-800">{p.slug}</td>
                    <td className="px-3 py-1.5 text-gray-600">{p.title}</td>
                    <td className="px-3 py-1.5">{p.matched ? <span className="text-green-600">✓</span> : <span className="text-gray-300">—</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="text-xs text-gray-500">
            {data.postsAvailable} blog post(s) available{data.catalogPostId ? `, catalog post #${data.catalogPostId}` : ''}. The
            command below targets the pages already in this site (highlighted).
          </p>

          <div>
            <div className="flex items-center justify-between mb-1">
              <span className="text-sm font-medium text-gray-700">Ready import command</span>
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(data.command);
                  setCopied(true);
                  setTimeout(() => setCopied(false), 1500);
                }}
                className="text-xs inline-flex items-center gap-1 text-gray-500 hover:text-gray-800"
              >
                {copied ? <Check className="w-3 h-3" /> : <Copy className="w-3 h-3" />}
                {copied ? 'Copied' : 'Copy'}
              </button>
            </div>
            <pre className="bg-gray-950 text-gray-200 text-xs rounded-lg p-3 overflow-x-auto whitespace-pre">{data.command}</pre>
            <p className="text-xs text-gray-400 mt-1">
              Replace <code>&lt;YOUR_WP_DB_PASSWORD&gt;</code> and run it in the app shell. Add <code>--publish</code> to
              publish immediately, or import to draft first and verify.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
