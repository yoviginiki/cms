import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Globe, FileText, Newspaper, ExternalLink } from 'lucide-react';
import { sites } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';

interface Site {
  id: string;
  name: string;
  slug: string;
  custom_domain?: string;
  status: string;
  pages_count?: number;
  posts_count?: number;
}

export default function Dashboard() {
  const navigate = useNavigate();
  const { data, isLoading, error } = useQuery<Site[]>({
    queryKey: ['sites'],
    queryFn: () => sites.list().then(r => r.data.data),
  });

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-lg font-medium text-base-content/90">Dashboard</h1>
        <p className="mt-0.5 text-[13px] text-base-content/40">Manage your sites</p>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <span className="loading loading-spinner loading-sm text-base-content/20"></span>
        </div>
      )}

      {error && (
        <div className="alert alert-error text-[13px]">Failed to load sites. Please try again.</div>
      )}

      {data && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.map((site) => (
            <div key={site.id} onClick={() => navigate(`/sites/${site.id}/pages`)}
              className="card bg-base-100 border border-base-300/40 hover:border-base-300/70 cursor-pointer transition-all">
              <div className="card-body p-5 gap-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                      <Globe className="h-4 w-4 text-primary" strokeWidth={1.5} />
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-base-content/90">{site.name}</h3>
                      <p className="text-[11px] text-base-content/30">/{site.slug}</p>
                    </div>
                  </div>
                  <StatusBadge status={site.status} />
                </div>

                <div className="flex items-center gap-5 text-[12px] text-base-content/40">
                  <span className="flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5" strokeWidth={1.5} />
                    {site.pages_count ?? 0} pages
                  </span>
                  <span className="flex items-center gap-1.5">
                    <Newspaper className="h-3.5 w-3.5" strokeWidth={1.5} />
                    {site.posts_count ?? 0} posts
                  </span>
                </div>

                <div className="flex items-center gap-3 pt-3 border-t border-base-300/20">
                  <button onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/pages`); }}
                    className="text-[12px] text-primary hover:text-primary/80 font-medium">Pages</button>
                  <button onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/posts`); }}
                    className="text-[12px] text-primary hover:text-primary/80 font-medium">Posts</button>
                  <button onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/settings`); }}
                    className="text-[12px] text-primary hover:text-primary/80 font-medium">Settings</button>
                  <a href={site.custom_domain ? `https://${site.custom_domain}` : '/site'}
                    target="_blank" rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="ml-auto flex items-center gap-1 text-[12px] text-base-content/40 hover:text-primary font-medium">
                    <ExternalLink className="h-3 w-3" strokeWidth={1.5} />
                    View Site
                  </a>
                </div>
              </div>
            </div>
          ))}

          <div onClick={() => {
              const name = window.prompt('Site name:');
              if (name) {
                sites.create({ name, slug: name.toLowerCase().replace(/\s+/g, '-') }).then((r) => {
                  navigate(`/sites/${r.data.data.id}/pages`);
                });
              }
            }}
            className="card border-2 border-dashed border-base-300/40 hover:border-base-300/70 cursor-pointer transition-all min-h-[180px] flex items-center justify-center">
            <div className="card-body items-center justify-center text-center p-5">
              <Plus className="h-8 w-8 text-base-content/15 mb-2" strokeWidth={1.5} />
              <span className="text-[13px] font-medium text-base-content/40">Create new site</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
