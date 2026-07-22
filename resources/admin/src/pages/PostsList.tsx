import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Edit, Trash2, Newspaper, Loader2, Search, ArrowUpDown, ArrowUp, ArrowDown, Copy, Check, ExternalLink } from 'lucide-react';
import { posts, categories as categoriesApi, sites, api } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Post {
  id: string;
  short_id: string;
  title: string;
  slug: string;
  status: string;
  category?: { id: string; name: string; slug?: string };
  grid_id?: string | null;
  grid?: { id: string; name: string; slug: string } | null;
  published_at: string | null;
  created_at: string;
  updated_at: string;
}

interface Category {
  id: string;
  name: string;
}

type SortField = 'published_at' | 'created_at' | 'updated_at' | 'title';
type SortDir = 'asc' | 'desc';

export default function PostsList() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<Post | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [sortField, setSortField] = useState<SortField>('published_at');
  const [sortDir, setSortDir] = useState<SortDir>('desc');
  const [copiedId, setCopiedId] = useState<string | null>(null);

  // Debounce search
  const searchTimeout = useMemo(() => {
    let timeout: ReturnType<typeof setTimeout>;
    return (value: string) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => setDebouncedSearch(value), 300);
    };
  }, []);

  const handleSearchChange = (value: string) => {
    setSearch(value);
    searchTimeout(value);
  };

  const { data, isLoading, error } = useQuery<Post[]>({
    queryKey: ['posts', siteId, statusFilter, categoryFilter, debouncedSearch, sortField, sortDir],
    queryFn: () => {
      const params: Record<string, unknown> = { sort: sortField, dir: sortDir };
      if (statusFilter !== 'all') params.status = statusFilter;
      if (categoryFilter !== 'all') params.category_id = categoryFilter;
      if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
      return posts.list(siteId, params).then(r => r.data.data);
    },
  });

  const { data: categories } = useQuery<Category[]>({
    queryKey: ['categories', siteId],
    queryFn: () => categoriesApi.list(siteId).then(r => r.data.data),
  });

  const { data: siteData } = useQuery<{ custom_domain?: string; slug?: string; settings?: Record<string, unknown> }>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then(r => r.data.data),
  });
  const publicBase = siteData?.custom_domain ? `https://${siteData.custom_domain}` : `https://ensodo.eu/${(siteData?.settings?.deploy_slug as string) || siteData?.slug || ''}`;

  const deleteMutation = useMutation({
    mutationFn: (postId: string) => posts.delete(siteId, postId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['posts', siteId] });
      setDeleteTarget(null);
    },
  });

  const duplicateMutation = useMutation({
    mutationFn: (postId: string) => api.post(`/sites/${siteId}/posts/${postId}/duplicate`),
    onSuccess: (r) => { queryClient.invalidateQueries({ queryKey: ['posts', siteId] }); navigate(`/sites/${siteId}/posts/${r.data.data.id}/edit`); },
    onError: (e: any) => alert(e?.response?.data?.message || 'Failed to duplicate post'),
  });

  const createMutation = useMutation({
    mutationFn: (title: string) => posts.create(siteId, { title, slug: title.toLowerCase().replace(/\s+/g, '-'), status: 'draft' }),
    onSuccess: (r) => {
      navigate(`/sites/${siteId}/posts/${r.data.data.id}/edit`);
    },
  });

  const handleCreate = () => {
    const title = window.prompt('Post title:');
    if (title?.trim()) {
      createMutation.mutate(title.trim());
    }
  };

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDir('desc');
    }
  };

  const SortIcon = ({ field }: { field: SortField }) => {
    if (sortField !== field) return <ArrowUpDown className="h-3 w-3 opacity-40" />;
    return sortDir === 'asc' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />;
  };

  const copyId = (id: string) => {
    navigator.clipboard.writeText(id);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 1500);
  };

  const resultCount = data?.length ?? 0;

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-lg font-medium text-base-content/90">Posts</h1>
          <p className="mt-0.5 text-[13px] text-base-content/40">Manage your blog posts</p>
        </div>
        <button onClick={handleCreate} disabled={createMutation.isPending} className="btn btn-primary btn-sm text-[12px] gap-1.5">
          {createMutation.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Plus className="h-3.5 w-3.5" />}
          Create post
        </button>
      </div>

      {/* Search & Filters */}
      <div className="flex flex-wrap items-center gap-2 mb-5">
        <label className="input input-bordered input-sm flex-1 min-w-[200px] max-w-sm flex items-center gap-2 text-[13px]">
          <Search className="h-3.5 w-3.5 text-base-content/30" />
          <input type="text" value={search} onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Search by title, slug, or ID..." className="grow bg-transparent" />
        </label>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
          className="select select-bordered select-sm text-[13px]">
          <option value="all">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
        <select value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)}
          className="select select-bordered select-sm text-[13px]">
          <option value="all">All categories</option>
          {categories?.map((cat) => (
            <option key={cat.id} value={cat.id}>{cat.name}</option>
          ))}
        </select>
        {data && (
          <span className="text-[11px] text-base-content/30 ml-auto">{resultCount} post{resultCount !== 1 ? 's' : ''}</span>
        )}
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <span className="loading loading-spinner loading-sm text-base-content/20"></span>
        </div>
      )}

      {error && (
        <div className="alert alert-error text-[13px]">Failed to load posts. Please try again.</div>
      )}

      {data && data.length === 0 && !debouncedSearch && statusFilter === 'all' && categoryFilter === 'all' && (
        <EmptyState icon={Newspaper} title="No posts yet" description="Create your first blog post to get started" actionLabel="Create post" onAction={handleCreate} />
      )}

      {data && data.length === 0 && (debouncedSearch || statusFilter !== 'all' || categoryFilter !== 'all') && (
        <div className="text-center py-16">
          <Search className="h-8 w-8 mx-auto mb-3 text-base-content/15" />
          <p className="text-[13px] text-base-content/40">No posts match your filters</p>
          <button onClick={() => { setSearch(''); setDebouncedSearch(''); setStatusFilter('all'); setCategoryFilter('all'); }}
            className="btn btn-ghost btn-xs text-primary mt-2">Clear filters</button>
        </div>
      )}

      {/* Mobile card view */}
      {data && data.length > 0 && (
        <div className="lg:hidden space-y-2">
          {data.map((post: Post) => (
            <div key={post.id} className="bg-base-100 rounded-lg border border-base-300/40 p-3">
              <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <button onClick={() => navigate(`/sites/${siteId}/posts/${post.id}/edit`)}
                    className="text-sm font-medium text-base-content/90 hover:text-primary text-left truncate block w-full">
                    {post.title}
                  </button>
                  <p className="text-[11px] text-base-content/30 truncate">/{post.slug}</p>
                  <div className="flex items-center gap-2 mt-1">
                    <StatusBadge status={post.status} />
                    {post.category?.name && (
                      <span className="text-[10px] text-base-content/40">{post.category.name}</span>
                    )}
                    {post.published_at && (
                      <span className="text-[10px] text-base-content/30">{new Date(post.published_at).toLocaleDateString()}</span>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <a href={`${publicBase}/${post.category?.slug ? post.category.slug + '/' : ''}${post.slug}`}
                    target="_blank" rel="noopener"
                    className="btn btn-ghost btn-sm btn-square text-base-content/40" title="View">
                    <ExternalLink className="h-4 w-4" />
                  </a>
                  <button onClick={() => navigate(`/sites/${siteId}/posts/${post.id}/edit`)}
                    className="btn btn-ghost btn-sm btn-square text-base-content/40" title="Edit">
                    <Edit className="h-4 w-4" />
                  </button>
                  <button onClick={() => duplicateMutation.mutate(post.id)}
                    className="btn btn-ghost btn-sm btn-square text-base-content/40 hover:text-success" title="Duplicate"
                    disabled={duplicateMutation.isPending}>
                    <Copy className="h-4 w-4" />
                  </button>
                  <button onClick={() => setDeleteTarget(post)}
                    className="btn btn-ghost btn-sm btn-square text-base-content/40 hover:text-error" title="Delete">
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Desktop table view */}
      {data && data.length > 0 && (
        <div className="overflow-x-auto rounded-box border border-base-300/40 hidden lg:block">
          <table className="table table-sm">
            <thead>
              <tr className="border-b border-base-300/40">
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider w-20">ID</th>
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider cursor-pointer select-none hover:text-base-content/60"
                  onClick={() => toggleSort('title')}>
                  <span className="inline-flex items-center gap-1">Title <SortIcon field="title" /></span>
                </th>
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider">Category</th>
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider">Grid</th>
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider">Status</th>
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider cursor-pointer select-none hover:text-base-content/60"
                  onClick={() => toggleSort('published_at')}>
                  <span className="inline-flex items-center gap-1">Published <SortIcon field="published_at" /></span>
                </th>
                <th className="text-[11px] font-medium text-base-content/30 uppercase tracking-wider cursor-pointer select-none hover:text-base-content/60"
                  onClick={() => toggleSort('updated_at')}>
                  <span className="inline-flex items-center gap-1">Updated <SortIcon field="updated_at" /></span>
                </th>
                <th className="text-right text-[11px] font-medium text-base-content/30 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              {data.map((post) => (
                <tr key={post.id} className="border-b border-base-300/20 hover:bg-base-300/10 transition-colors">
                  <td>
                    <button onClick={() => copyId(post.id)}
                      className="inline-flex items-center gap-1 font-mono text-[11px] text-base-content/30 hover:text-base-content/60 transition-colors"
                      title={`Full ID: ${post.id}\nClick to copy`}>
                      {post.short_id}
                      {copiedId === post.id ? <Check className="h-3 w-3 text-success" /> : <Copy className="h-3 w-3 opacity-0 group-hover:opacity-100" />}
                    </button>
                  </td>
                  <td>
                    <button onClick={() => navigate(`/sites/${siteId}/posts/${post.id}/edit`)}
                      className="text-[13px] font-medium text-base-content/90 hover:text-primary hover:underline text-left cursor-pointer">{post.title}</button>
                    <p className="text-[11px] text-base-content/30 truncate max-w-xs">/{post.slug}</p>
                  </td>
                  <td className="text-[13px] text-base-content/50">
                    {post.category?.name ?? <span className="text-base-content/20">--</span>}
                  </td>
                  <td>
                    {post.grid ? (
                      <span className="badge badge-sm badge-ghost text-[10px] font-medium" title={`Grid: ${post.grid.name}`}>
                        {post.grid.name}
                      </span>
                    ) : (
                      <span className="text-[11px] text-base-content/20">default</span>
                    )}
                  </td>
                  <td><StatusBadge status={post.status} /></td>
                  <td className="text-[13px] text-base-content/40">
                    {post.published_at ? new Date(post.published_at).toLocaleDateString() : <span className="text-base-content/20">--</span>}
                  </td>
                  <td className="text-[11px] text-base-content/30">
                    {new Date(post.updated_at).toLocaleDateString()}
                  </td>
                  <td className="text-right">
                    <div className="flex items-center justify-end gap-0.5">
                      <a href={`${publicBase}/${post.category?.slug ? post.category.slug + '/' : ''}${post.slug}`}
                        target="_blank" rel="noopener"
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-success" title="View post">
                        <ExternalLink className="h-3.5 w-3.5" />
                      </a>
                      <button onClick={() => navigate(`/sites/${siteId}/posts/${post.id}/edit`)}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-primary" title="Edit">
                        <Edit className="h-3.5 w-3.5" />
                      </button>
                      <button onClick={() => duplicateMutation.mutate(post.id)}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-success" title="Duplicate"
                        disabled={duplicateMutation.isPending}>
                        <Copy className="h-3.5 w-3.5" />
                      </button>
                      <button onClick={() => setDeleteTarget(post)}
                        className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-error" title="Delete">
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete post"
        message={`Are you sure you want to delete "${deleteTarget?.title}"? This action cannot be undone.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}
