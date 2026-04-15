import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Globe, FileText, Newspaper, Loader2 } from 'lucide-react';
import { sites } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';

interface Site {
  id: string;
  name: string;
  slug: string;
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
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">Manage your sites</p>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          Failed to load sites. Please try again.
        </div>
      )}

      {data && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {data.map((site) => (
            <div
              key={site.id}
              onClick={() => navigate(`/sites/${site.id}/pages`)}
              className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 cursor-pointer hover:shadow-md hover:border-gray-300 transition-all"
            >
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                    <Globe className="h-5 w-5 text-blue-600" />
                  </div>
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">{site.name}</h3>
                    <p className="text-sm text-gray-500">/{site.slug}</p>
                  </div>
                </div>
                <StatusBadge status={site.status} />
              </div>

              <div className="flex items-center gap-6 mb-4 text-sm text-gray-600">
                <div className="flex items-center gap-1.5">
                  <FileText className="h-4 w-4 text-gray-400" />
                  <span>{site.pages_count ?? 0} pages</span>
                </div>
                <div className="flex items-center gap-1.5">
                  <Newspaper className="h-4 w-4 text-gray-400" />
                  <span>{site.posts_count ?? 0} posts</span>
                </div>
              </div>

              <div className="flex items-center gap-3 pt-4 border-t border-gray-100">
                <button
                  onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/pages`); }}
                  className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                >
                  Pages
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/posts`); }}
                  className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                >
                  Posts
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/settings`); }}
                  className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                >
                  Settings
                </button>
              </div>
            </div>
          ))}

          <div
            onClick={() => {
              const name = window.prompt('Site name:');
              if (name) {
                sites.create({ name, slug: name.toLowerCase().replace(/\s+/g, '-') }).then((r) => {
                  navigate(`/sites/${r.data.data.id}/pages`);
                });
              }
            }}
            className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 p-6 cursor-pointer hover:border-gray-400 hover:bg-gray-50 transition-all min-h-[200px]"
          >
            <Plus className="h-10 w-10 text-gray-400 mb-3" />
            <span className="text-sm font-medium text-gray-600">Create New Site</span>
          </div>
        </div>
      )}
    </div>
  );
}
