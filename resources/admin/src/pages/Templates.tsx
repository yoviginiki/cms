import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Layout, Plus, Loader2, Trash2, FileText, Globe, FolderTree, Film, Grid3x3, Search, AlertTriangle } from 'lucide-react';
import { themeTemplates as templatesApi } from '@/lib/api';

interface Template {
  id: string;
  name: string;
  slug: string;
  type: string;
  category_id?: string;
  post_format?: string;
  is_default: boolean;
  category?: { id: string; name: string; slug: string };
  created_at: string;
}

const TYPE_ICONS: Record<string, typeof Layout> = {
  post: FileText,
  archive: Grid3x3,
  header: Globe,
  footer: Globe,
  '404': AlertTriangle,
  search: Search,
};

const TYPE_LABELS: Record<string, string> = {
  post: 'Single Post',
  archive: 'Category Archive',
  header: 'Global Header',
  footer: 'Global Footer',
  '404': '404 Page',
  search: 'Search Results',
};

const FORMAT_LABELS: Record<string, string> = {
  standard: 'Standard',
  video: 'Video',
  gallery: 'Gallery',
  audio: 'Audio',
  link: 'Link',
};

export default function Templates() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [showCreate, setShowCreate] = useState(false);
  const [newName, setNewName] = useState('');
  const [newType, setNewType] = useState('post');
  const [newCategoryId, setNewCategoryId] = useState('');
  const [newPostFormat, setNewPostFormat] = useState('');
  const [newIsDefault, setNewIsDefault] = useState(false);

  const { data: templatesList, isLoading } = useQuery<Template[]>({
    queryKey: ['templates', siteId],
    queryFn: () => templatesApi.list(siteId).then((r: any) => r.data?.data || []),
  });

  const createMut = useMutation({
    mutationFn: (data: Record<string, unknown>) => templatesApi.create(siteId, data),
    onSuccess: (r: any) => {
      queryClient.invalidateQueries({ queryKey: ['templates', siteId] });
      setShowCreate(false);
      setNewName('');
      navigate(`/sites/${siteId}/templates/${r.data.data.id}/edit`);
    },
  });

  const deleteMut = useMutation({
    mutationFn: (id: string) => templatesApi.delete(siteId, id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates', siteId] }),
  });

  const handleCreate = () => {
    createMut.mutate({
      name: newName,
      type: newType,
      category_id: newCategoryId || null,
      post_format: newPostFormat || null,
      is_default: newIsDefault,
    });
  };

  const grouped = (templatesList || []).reduce((acc, t) => {
    acc[t.type] = acc[t.type] || [];
    acc[t.type].push(t);
    return acc;
  }, {} as Record<string, Template[]>);

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <Layout className="h-6 w-6 text-indigo-500" /> Theme Builder
          </h1>
          <p className="mt-1 text-sm text-gray-500">
            Design templates for posts, archives, headers, and footers
          </p>
        </div>
        <button onClick={() => setShowCreate(true)}
          className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
          <Plus className="h-4 w-4" /> New Template
        </button>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      )}

      {!isLoading && (!templatesList || templatesList.length === 0) && (
        <div className="text-center py-16 bg-white rounded-xl border border-gray-200">
          <Layout className="h-12 w-12 mx-auto mb-4 text-gray-200" />
          <h3 className="text-lg font-semibold text-gray-700 mb-1">No templates yet</h3>
          <p className="text-sm text-gray-400 mb-4">Create your first template to control how posts and pages look</p>
          <button onClick={() => setShowCreate(true)}
            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
            <Plus className="h-4 w-4" /> Create Template
          </button>
        </div>
      )}

      {/* Grouped by type */}
      {Object.entries(grouped).map(([type, items]) => {
        const Icon = TYPE_ICONS[type] || Layout;
        return (
          <div key={type} className="mb-8">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Icon className="h-4 w-4" /> {TYPE_LABELS[type] || type} Templates ({items.length})
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {items.map(template => (
                <div key={template.id}
                  className={`bg-white rounded-xl border p-4 hover:shadow-md transition-shadow cursor-pointer ${
                    template.is_default ? 'border-indigo-300 ring-1 ring-indigo-200' : 'border-gray-200'
                  }`}
                  onClick={() => navigate(`/sites/${siteId}/templates/${template.id}/edit`)}
                >
                  <div className="flex items-start justify-between mb-2">
                    <div>
                      <h3 className="font-semibold text-gray-900">{template.name}</h3>
                      <div className="flex items-center gap-1.5 mt-1">
                        {template.is_default && (
                          <span className="text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-medium">Default</span>
                        )}
                        {template.category && (
                          <span className="text-[10px] bg-green-50 text-green-600 px-1.5 py-0.5 rounded flex items-center gap-0.5">
                            <FolderTree className="h-2.5 w-2.5" /> {template.category.name}
                          </span>
                        )}
                        {template.post_format && template.post_format !== 'standard' && (
                          <span className="text-[10px] bg-purple-50 text-purple-600 px-1.5 py-0.5 rounded flex items-center gap-0.5">
                            <Film className="h-2.5 w-2.5" /> {FORMAT_LABELS[template.post_format] || template.post_format}
                          </span>
                        )}
                      </div>
                    </div>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        if (confirm(`Delete "${template.name}"?`)) deleteMut.mutate(template.id);
                      }}
                      className="p-1 text-gray-300 hover:text-red-500 transition-colors"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        );
      })}

      {/* Create dialog */}
      {showCreate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowCreate(false)}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-semibold mb-4">New Template</h3>
            <div className="space-y-3">
              <div>
                <label className="text-[11px] text-gray-500 mb-1 block">Template Name</label>
                <input value={newName} onChange={e => setNewName(e.target.value)}
                  className="input input-bordered input-sm w-full" placeholder="e.g. Video Post, News Archive" />
              </div>
              <div>
                <label className="text-[11px] text-gray-500 mb-1 block">Template Type</label>
                <select value={newType} onChange={e => setNewType(e.target.value)}
                  className="select select-bordered select-sm w-full">
                  <option value="post">Single Post</option>
                  <option value="archive">Category Archive</option>
                  <option value="header">Global Header</option>
                  <option value="footer">Global Footer</option>
                  <option value="404">404 Page</option>
                  <option value="search">Search Results</option>
                </select>
              </div>
              {(newType === 'post' || newType === 'archive') && (
                <div>
                  <label className="text-[11px] text-gray-500 mb-1 block">Category (optional)</label>
                  <select value={newCategoryId} onChange={e => setNewCategoryId(e.target.value)}
                    className="select select-bordered select-sm w-full">
                    <option value="">All Categories</option>
                  </select>
                  <p className="text-[10px] text-gray-300 mt-0.5">Leave empty for a global template</p>
                </div>
              )}
              {newType === 'post' && (
                <div>
                  <label className="text-[11px] text-gray-500 mb-1 block">Post Format (optional)</label>
                  <select value={newPostFormat} onChange={e => setNewPostFormat(e.target.value)}
                    className="select select-bordered select-sm w-full">
                    <option value="">All Formats</option>
                    <option value="video">Video</option>
                    <option value="gallery">Gallery</option>
                    <option value="audio">Audio</option>
                    <option value="link">Link</option>
                  </select>
                </div>
              )}
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={newIsDefault} onChange={e => setNewIsDefault(e.target.checked)}
                  className="checkbox checkbox-sm" />
                <span className="text-[11px] text-gray-500">Set as default for this type</span>
              </label>
            </div>
            <div className="flex justify-end gap-2 mt-5">
              <button onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm text-gray-700 border rounded-lg">Cancel</button>
              <button onClick={handleCreate} disabled={!newName.trim() || createMut.isPending}
                className="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                {createMut.isPending ? 'Creating...' : 'Create'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
