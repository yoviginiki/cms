import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { GripVertical, Trash2, Plus, Search, FileText, Type, ImageIcon, Check } from 'lucide-react';
import { posts as postsApi, categories as categoriesApi } from '@/lib/api';
import type { Step2Structure, StructureArticle } from '../../types';

interface Props {
  data: Step2Structure | null;
  onChange: (data: Step2Structure) => void;
  readOnly?: boolean;
  siteId: string;
}

const RHYTHM_COLORS: Record<string, string> = {
  dense: 'badge-error',
  medium: 'badge-warning',
  breath: 'badge-success',
};

type AddMode = 'cms' | 'custom';

export default function StructureArtifact({ data, onChange, readOnly, siteId }: Props) {
  const articles = data?.articles || [];
  const totalPages = articles.reduce((sum, a) => sum + a.pages, 0);

  const [addMode, setAddMode] = useState<AddMode>('cms');
  const [showPicker, setShowPicker] = useState(false);
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [customTitle, setCustomTitle] = useState('');
  const [customText, setCustomText] = useState('');

  // Load categories
  const { data: cats } = useQuery({
    queryKey: ['categories', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data?.data ?? r.data.data ?? []),
    enabled: !!siteId && showPicker,
  });

  // Load posts
  const { data: postsList, isLoading: postsLoading } = useQuery({
    queryKey: ['wizard-posts', siteId, search, categoryFilter],
    queryFn: () => {
      const params: Record<string, unknown> = { per_page: 50 };
      if (search) params.search = search;
      if (categoryFilter) params.category_id = categoryFilter;
      return postsApi.list(siteId, params).then((r: any) => r.data.data?.data ?? r.data.data ?? []);
    },
    enabled: !!siteId && showPicker && addMode === 'cms',
  });

  // Already added slugs
  const addedSlugs = new Set(articles.map(a => a.slug));

  const update = (newArticles: StructureArticle[]) => {
    onChange({ articles: newArticles });
  };

  const updateArticle = (idx: number, patch: Partial<StructureArticle>) => {
    const next = [...articles];
    next[idx] = { ...next[idx], ...patch };
    if (patch.title && !next[idx].slug) {
      next[idx].slug = patch.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
    update(next);
  };

  const removeArticle = (idx: number) => {
    const next = [...articles];
    next.splice(idx, 1);
    update(next);
  };

  const moveArticle = (from: number, to: number) => {
    if (to < 0 || to >= articles.length) return;
    const next = [...articles];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    update(next);
  };

  const addPostAsArticle = (post: any) => {
    const slug = post.slug || post.id.substring(0, 8);
    if (addedSlugs.has(slug)) return;

    const wordCount = 0; // We don't have word count from list API, estimate pages
    const newArticle: StructureArticle = {
      slug,
      title: post.title || 'Untitled',
      pages: 2,
      rhythm: 'medium',
      role: '',
      justification: '',
    };
    update([...articles, newArticle]);
  };

  const addCustomArticle = () => {
    const hasTitle = customTitle.trim();
    const hasText = customText.trim();
    if (!hasTitle && !hasText) return;

    const title = hasTitle ? customTitle.trim() : customText.trim().substring(0, 60);
    const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || `custom-${Date.now()}`;
    const newArticle: StructureArticle = {
      slug,
      title,
      pages: 2,
      rhythm: 'medium',
      role: '',
      justification: hasText ? customText.trim().substring(0, 200) : '',
    };
    update([...articles, newArticle]);
    setCustomTitle('');
    setCustomText('');
  };

  return (
    <div>
      {/* Summary bar */}
      <div className="flex items-center justify-between mb-3 px-1">
        <div className="text-[15px] text-base-content/40">
          {articles.length} article{articles.length !== 1 ? 's' : ''} · {totalPages} pages
        </div>
        <div className="flex gap-1.5 text-[15px]">
          {['dense', 'medium', 'breath'].map(r => {
            const count = articles.filter(a => a.rhythm === r).length;
            return count > 0 ? (
              <span key={r} className={`badge badge-xs ${RHYTHM_COLORS[r]}`}>{r} ×{count}</span>
            ) : null;
          })}
        </div>
      </div>

      {/* Articles list */}
      <div className="space-y-2">
        {articles.map((article, idx) => (
          <div key={idx} className="bg-base-200/40 rounded-lg p-3 border border-base-300/20">
            <div className="flex items-start gap-2">
              {!readOnly && (
                <div className="flex flex-col items-center gap-0.5 pt-1">
                  <button onClick={() => moveArticle(idx, idx - 1)} disabled={idx === 0}
                    className="btn btn-ghost btn-sm btn-square opacity-30 hover:opacity-100">
                    <span className="text-[14px]">↑</span>
                  </button>
                  <GripVertical size={12} className="text-base-content/20" />
                  <button onClick={() => moveArticle(idx, idx + 1)} disabled={idx === articles.length - 1}
                    className="btn btn-ghost btn-sm btn-square opacity-30 hover:opacity-100">
                    <span className="text-[14px]">↓</span>
                  </button>
                </div>
              )}

              <div className="flex-1 space-y-2">
                <div className="flex items-center gap-2">
                  <span className="text-[14px] text-base-content/25 font-mono w-4 shrink-0">{idx + 1}</span>
                  <input
                    value={article.title}
                    onChange={e => updateArticle(idx, { title: e.target.value })}
                    disabled={readOnly}
                    placeholder="Article title"
                    className="input input-bordered input-sm flex-1 text-[14px] font-medium"
                  />
                  <select
                    value={article.rhythm}
                    onChange={e => updateArticle(idx, { rhythm: e.target.value as StructureArticle['rhythm'] })}
                    disabled={readOnly}
                    className={`select select-sm border-0 font-medium text-[14px] ${RHYTHM_COLORS[article.rhythm]} bg-transparent`}
                  >
                    <option value="dense">Dense</option>
                    <option value="medium">Medium</option>
                    <option value="breath">Breath</option>
                  </select>
                  <div className="flex items-center gap-1 bg-base-300/20 rounded px-1">
                    <button onClick={() => updateArticle(idx, { pages: Math.max(1, article.pages - 1) })}
                      disabled={readOnly} className="btn btn-ghost btn-sm px-1 text-[14px]">−</button>
                    <span className="text-[12px] font-mono w-4 text-center">{article.pages}</span>
                    <button onClick={() => updateArticle(idx, { pages: article.pages + 1 })}
                      disabled={readOnly} className="btn btn-ghost btn-sm px-1 text-[14px]">+</button>
                    <span className="text-[15px] text-base-content/30">pg</span>
                  </div>
                  {!readOnly && (
                    <button onClick={() => removeArticle(idx)}
                      className="btn btn-ghost btn-sm btn-square text-base-content/20 hover:text-error">
                      <Trash2 size={12} />
                    </button>
                  )}
                </div>
                <div className="flex gap-2 ml-6">
                  <input
                    value={article.role}
                    onChange={e => updateArticle(idx, { role: e.target.value })}
                    disabled={readOnly}
                    placeholder="Role in issue arc"
                    className="input input-bordered input-sm flex-1 text-[15px] text-base-content/60"
                  />
                </div>
                {article.justification && (
                  <div className="ml-6 text-[14px] text-base-content/35 italic leading-relaxed">
                    {article.justification}
                  </div>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Add article section */}
      {!readOnly && (
        <div className="mt-3">
          {!showPicker ? (
            <button onClick={() => setShowPicker(true)}
              className="btn btn-ghost btn-sm w-full text-[15px] gap-1 text-base-content/40 border border-dashed border-base-300/30">
              <Plus size={12} /> Add articles
            </button>
          ) : (
            <div className="border border-base-300/30 rounded-lg overflow-hidden">
              {/* Tabs: CMS / Custom */}
              <div className="flex border-b border-base-300/20">
                <button onClick={() => setAddMode('cms')}
                  className={`flex-1 px-3 py-1.5 text-[15px] font-medium flex items-center justify-center gap-1 ${
                    addMode === 'cms' ? 'bg-primary/10 text-primary border-b-2 border-primary' : 'text-base-content/40'
                  }`}>
                  <FileText size={11} /> From CMS
                </button>
                <button onClick={() => setAddMode('custom')}
                  className={`flex-1 px-3 py-1.5 text-[15px] font-medium flex items-center justify-center gap-1 ${
                    addMode === 'custom' ? 'bg-primary/10 text-primary border-b-2 border-primary' : 'text-base-content/40'
                  }`}>
                  <Type size={11} /> Custom text
                </button>
              </div>

              {/* CMS post picker */}
              {addMode === 'cms' && (
                <div className="p-2">
                  <div className="flex gap-2 mb-2">
                    <label className="input input-bordered input-sm flex-1 flex items-center gap-1">
                      <Search size={11} className="text-base-content/30" />
                      <input type="text" value={search} onChange={e => setSearch(e.target.value)}
                        placeholder="Search posts..." className="grow bg-transparent text-[15px]" />
                    </label>
                    <select value={categoryFilter} onChange={e => setCategoryFilter(e.target.value)}
                      className="select select-bordered select-sm text-[15px]">
                      <option value="">All categories</option>
                      {(cats || []).map((c: any) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>

                  <div className="max-h-48 overflow-y-auto space-y-0.5">
                    {postsLoading && (
                      <div className="text-center py-4">
                        <span className="loading loading-spinner loading-xs text-base-content/20" />
                      </div>
                    )}
                    {postsList && postsList.length === 0 && (
                      <div className="text-[15px] text-base-content/25 text-center py-4">No posts found</div>
                    )}
                    {(postsList || []).map((post: any) => {
                      const isAdded = addedSlugs.has(post.slug || post.id.substring(0, 8));
                      return (
                        <div key={post.id}
                          onClick={() => !isAdded && addPostAsArticle(post)}
                          className={`flex items-center gap-2 px-2 py-1.5 rounded transition-colors ${
                            isAdded
                              ? 'bg-success/5 text-base-content/30 cursor-default'
                              : 'hover:bg-base-200/50 cursor-pointer'
                          }`}>
                          {post.featured_image ? (
                            <ImageIcon size={11} className="text-primary/40 shrink-0" />
                          ) : (
                            <FileText size={11} className="text-base-content/20 shrink-0" />
                          )}
                          <div className="flex-1 min-w-0">
                            <div className="text-[15px] font-medium truncate">{post.title}</div>
                            {post.category && (
                              <div className="text-[15px] text-base-content/30">{post.category.name}</div>
                            )}
                          </div>
                          {isAdded ? (
                            <Check size={11} className="text-success shrink-0" />
                          ) : (
                            <Plus size={11} className="text-base-content/20 shrink-0" />
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Custom text input */}
              {addMode === 'custom' && (
                <div className="p-2 space-y-2">
                  <input
                    value={customTitle}
                    onChange={e => setCustomTitle(e.target.value)}
                    placeholder="Article title"
                    className="input input-bordered input-sm w-full text-[15px]"
                  />
                  <textarea
                    value={customText}
                    onChange={e => setCustomText(e.target.value)}
                    placeholder="Paste text content (optional)..."
                    className="textarea textarea-bordered textarea-sm w-full h-20 text-[15px]"
                  />
                  <button onClick={addCustomArticle} disabled={!customTitle.trim() && !customText.trim()}
                    className="btn btn-primary btn-sm w-full text-[14px]">
                    Add custom article
                  </button>
                </div>
              )}

              {/* Close picker */}
              <div className="px-2 py-1.5 border-t border-base-300/15">
                <button onClick={() => setShowPicker(false)}
                  className="btn btn-ghost btn-sm text-[14px] text-base-content/30">
                  Close picker
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Rhythm visualization */}
      {articles.length > 0 && (
        <div className="mt-4 pt-3 border-t border-base-300/15">
          <div className="text-[15px] text-base-content/25 uppercase tracking-wider mb-2">Rhythm map</div>
          <div className="flex gap-0.5 h-6">
            {articles.map((a, i) => (
              <div
                key={i}
                style={{ flex: a.pages }}
                className={`rounded-sm flex items-center justify-center text-[14px] font-medium ${
                  a.rhythm === 'dense' ? 'bg-error/20 text-error/70' :
                  a.rhythm === 'medium' ? 'bg-warning/20 text-warning/70' :
                  'bg-success/20 text-success/70'
                }`}
                title={`${a.title || 'Untitled'}: ${a.rhythm} (${a.pages}p)`}
              >
                {a.pages >= 2 ? a.rhythm[0].toUpperCase() : ''}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
