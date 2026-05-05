import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Loader2, TrendingUp, TrendingDown, Eye, Globe, Smartphone, Monitor, Tablet } from 'lucide-react';
import { api } from '@/lib/api';

interface AnalyticsData {
  period_days: number;
  total_views: number;
  today: number;
  yesterday: number;
  change_pct: number;
  views_per_day: Array<{ date: string; views: number }>;
  top_pages: Array<{ path: string; views: number }>;
  top_referrers: Array<{ referrer: string; views: number }>;
  devices: Record<string, number>;
  browsers: Record<string, number>;
}

export default function Analytics() {
  const { siteId = '' } = useParams();
  const [days, setDays] = useState(30);

  const { data, isLoading } = useQuery<AnalyticsData>({
    queryKey: ['analytics', siteId, days],
    queryFn: () => api.get(`/sites/${siteId}/analytics?days=${days}`).then(r => r.data.data),
  });

  if (isLoading) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  if (!data) return null;

  const maxDayViews = Math.max(...(data.views_per_day.map(d => d.views) || [1]));
  const deviceTotal = Object.values(data.devices).reduce((a, b) => a + b, 0) || 1;

  return (
    <div className="max-w-6xl mx-auto py-6 px-4">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Analytics</h1>
          <p className="text-sm text-gray-500">Site traffic and content performance</p>
        </div>
        <div className="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
          {[7, 30, 90].map(d => (
            <button key={d} onClick={() => setDays(d)}
              className={`px-3 py-1 text-xs font-medium rounded-md ${days === d ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}>
              {d}d
            </button>
          ))}
        </div>
      </div>

      {/* Overview cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <StatCard label="Total Views" value={data.total_views.toLocaleString()} icon={Eye} />
        <StatCard label="Today" value={data.today.toLocaleString()} icon={TrendingUp}
          change={data.change_pct} />
        <StatCard label="Yesterday" value={data.yesterday.toLocaleString()} icon={Globe} />
        <StatCard label="Avg/Day" value={Math.round(data.total_views / Math.max(data.period_days, 1)).toLocaleString()} icon={Monitor} />
      </div>

      {/* Views chart */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
        <h3 className="font-semibold text-gray-900 mb-4">Views over time</h3>
        {data.views_per_day.length === 0 ? (
          <div className="text-center py-8 text-gray-400">
            <Eye className="h-8 w-8 mx-auto mb-2" />
            <p className="text-sm">No data yet. Views will appear as visitors browse your site.</p>
          </div>
        ) : (
          <div className="flex items-end gap-px h-40">
            {data.views_per_day.map((d, i) => (
              <div key={i} className="flex-1 flex flex-col items-center group relative">
                <div className="w-full bg-blue-500 rounded-t-sm transition-all hover:bg-blue-600"
                  style={{ height: `${Math.max(2, (d.views / maxDayViews) * 100)}%` }} />
                <div className="absolute -top-8 bg-gray-900 text-white text-[10px] px-2 py-0.5 rounded opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none">
                  {d.date}: {d.views} views
                </div>
              </div>
            ))}
          </div>
        )}
        {data.views_per_day.length > 0 && (
          <div className="flex justify-between mt-1 text-[10px] text-gray-400">
            <span>{data.views_per_day[0]?.date}</span>
            <span>{data.views_per_day[data.views_per_day.length - 1]?.date}</span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {/* Top pages */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <h3 className="font-semibold text-gray-900 mb-3">Top Pages</h3>
          {data.top_pages.length === 0 ? (
            <p className="text-sm text-gray-400">No page views yet</p>
          ) : (
            <div className="space-y-2">
              {data.top_pages.slice(0, 10).map((p, i) => (
                <div key={i} className="flex items-center gap-2">
                  <span className="text-xs text-gray-400 w-5">{i + 1}.</span>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-gray-700 truncate font-mono">{p.path}</span>
                      <span className="text-sm font-medium text-gray-900 ml-2 shrink-0">{p.views}</span>
                    </div>
                    <div className="w-full bg-gray-100 rounded-full h-1 mt-1">
                      <div className="bg-blue-500 h-1 rounded-full" style={{ width: `${(p.views / (data.top_pages[0]?.views || 1)) * 100}%` }} />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Top referrers */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <h3 className="font-semibold text-gray-900 mb-3">Top Referrers</h3>
          {data.top_referrers.length === 0 ? (
            <p className="text-sm text-gray-400">No referrer data yet</p>
          ) : (
            <div className="space-y-2">
              {data.top_referrers.map((r, i) => {
                let domain = r.referrer;
                try { domain = new URL(r.referrer).hostname; } catch {}
                return (
                  <div key={i} className="flex items-center justify-between">
                    <span className="text-sm text-gray-700 truncate">{domain}</span>
                    <span className="text-sm font-medium text-gray-900 ml-2">{r.views}</span>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* Devices & Browsers */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <h3 className="font-semibold text-gray-900 mb-3">Devices</h3>
          <div className="space-y-3">
            {Object.entries(data.devices).map(([device, views]) => {
              const Icon = device === 'mobile' ? Smartphone : device === 'tablet' ? Tablet : Monitor;
              const pct = Math.round((views / deviceTotal) * 100);
              return (
                <div key={device} className="flex items-center gap-3">
                  <Icon className="h-4 w-4 text-gray-400 shrink-0" />
                  <div className="flex-1">
                    <div className="flex justify-between text-sm mb-0.5">
                      <span className="capitalize text-gray-700">{device}</span>
                      <span className="text-gray-500">{pct}% ({views})</span>
                    </div>
                    <div className="w-full bg-gray-100 rounded-full h-2">
                      <div className="bg-green-500 h-2 rounded-full" style={{ width: `${pct}%` }} />
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <h3 className="font-semibold text-gray-900 mb-3">Browsers</h3>
          <div className="space-y-3">
            {Object.entries(data.browsers).map(([browser, views]) => {
              const pct = Math.round((views / deviceTotal) * 100);
              const colors: Record<string, string> = { chrome: '#4285f4', firefox: '#ff7139', safari: '#007aff', edge: '#0078d7', other: '#9ca3af' };
              return (
                <div key={browser} className="flex items-center gap-3">
                  <span className="w-4 h-4 rounded-full shrink-0" style={{ backgroundColor: colors[browser] || colors.other }} />
                  <div className="flex-1">
                    <div className="flex justify-between text-sm mb-0.5">
                      <span className="capitalize text-gray-700">{browser}</span>
                      <span className="text-gray-500">{pct}% ({views})</span>
                    </div>
                    <div className="w-full bg-gray-100 rounded-full h-2">
                      <div className="h-2 rounded-full" style={{ width: `${pct}%`, backgroundColor: colors[browser] || colors.other }} />
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

function StatCard({ label, value, icon: Icon, change }: { label: string; value: string; icon: React.ElementType; change?: number }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <div className="flex items-center justify-between mb-2">
        <span className="text-xs text-gray-500">{label}</span>
        <Icon className="h-4 w-4 text-gray-400" />
      </div>
      <p className="text-2xl font-bold text-gray-900">{value}</p>
      {change !== undefined && change !== 0 && (
        <div className={`flex items-center gap-1 mt-1 text-xs ${change > 0 ? 'text-green-600' : 'text-red-600'}`}>
          {change > 0 ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
          {change > 0 ? '+' : ''}{change}% vs yesterday
        </div>
      )}
    </div>
  );
}
