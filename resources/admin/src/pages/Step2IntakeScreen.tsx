import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Search, Plus, Trash2, GripVertical, ImageIcon, FileText, Type, Film, Loader2 } from 'lucide-react';
import { issueComposer, posts as postsApi, categories as categoriesApi } from '@/lib/api';
import IssueComposerLayout from './IssueComposerLayout';

interface ContentItem {
  id: string;
  source_type: string;
  source_id: string | null;
  extra_payload: Record<string, unknown> | null;
  importance: string;
  role_hint: string;
  editor_note: string | null;
  ai_decision: string;
  position: number;
}

interface PostItem {
  id: string;
  title: string;
  slug: string;
  excerpt: string | null;
  status: string;
  category?: { id: string; name: string };
  published_at: string | null;
}

export default function Step2IntakeScreen() {
  const { siteId = '', issueId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [selectedPostIds, setSelectedPostIds] = useState<Set<string>>(new Set());
  const [showExtraModal, setShowExtraModal] = useState(false);
  const [extraText, setExtraText] = useState('');
  const [extraCaption, setExtraCaption] = useState('');

  // Load issue
  const { data: issue } = useQuery({
    queryKey: ['issue', siteId, issueId],
    queryFn: () => issueComposer.get(siteId, issueId).then((r: any) => r.data.data),
  });

  // Load posts for picker
  const { data: postsList, isLoading: postsLoading } = useQuery({
    queryKey: ['posts-picker', siteId, search, categoryFilter],
    queryFn: () => {
      const params: Record<string, unknown> = { per_page: 50 };
      if (search) params.search = search;
      if (categoryFilter) params.category_id = categoryFilter;
      return postsApi.list(siteId, params).then((r: any) => r.data.data);
    },
  });

  // Load categories
  const { data: categories } = useQuery({
    queryKey: ['categories', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data),
  });

  // Content items from issue
  const items: ContentItem[] = issue?.content_items || [];
  const trayPostIds = new Set(items.filter((i: ContentItem) => i.source_type === 'post').map((i: ContentItem) => i.source_id));

  // Add posts to tray
  const [addError, setAddError] = useState('');
  const addItemsMutation = useMutation({
    mutationFn: async (postIds: string[]) => {
      setAddError('');
      for (const postId of postIds) {
        await issueComposer.addItem(siteId, issueId, {
          source_type: 'post',
          source_id: postId,
          importance: 'should',
          role_hint: 'none',
        });
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] });
      setSelectedPostIds(new Set());
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.response?.data?.errors ? JSON.stringify(err.response.data.errors) : err.message;
      setAddError(msg);
      console.error('Add items failed:', err.response?.data || err);
    },
  });

  // Add extra text
  const addExtraMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => issueComposer.addItem(siteId, issueId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] });
      setShowExtraModal(false);
      setExtraText('');
      setExtraCaption('');
    },
  });

  // Update item
  const updateItemMutation = useMutation({
    mutationFn: ({ itemId, data }: { itemId: string; data: Record<string, unknown> }) =>
      issueComposer.updateItem(siteId, issueId, itemId, data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] }),
  });

  // Remove item
  const removeItemMutation = useMutation({
    mutationFn: (itemId: string) => issueComposer.removeItem(siteId, issueId, itemId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['issue', siteId, issueId] }),
  });

  // Advance to curation
  const advanceMutation = useMutation({
    mutationFn: () => issueComposer.update(siteId, issueId, { status: 'curating' }),
    onSuccess: () => navigate(`/sites/${siteId}/issue-composer/${issueId}/curation`),
  });

  const handleAddSelected = () => {
    const ids = Array.from(selectedPostIds);
    if (ids.length > 0) addItemsMutation.mutate(ids);
  };

  const handleAddExtra = () => {
    if (!extraText.trim()) return;
    addExtraMutation.mutate({
      source_type: 'extra_text',
      extra_payload: { text: extraText.trim(), caption: extraCaption.trim() || null },
      importance: 'should',
      role_hint: 'none',
    });
  };

  const togglePost = (id: string) => {
    setSelectedPostIds(prev => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  // Gate check
  const mustCount = items.filter((i: ContentItem) => i.importance === 'must').length;
  const canAdvance = items.length >= 5 && mustCount >= 1;
  const totalWords = items.length * 800; // rough estimate
  const postCount = items.filter((i: ContentItem) => i.source_type === 'post').length;
  const extraCount = items.length - postCount;

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  return (
    <IssueComposerLayout currentStep="intake" issueId={issueId} issueStatus={(issue as any)?.status}>
      <div className="flex flex-col h-full" data-theme={adminTheme}>
        {/* Header */}
        <div className="px-6 py-4 border-b border-base-300/20 shrink-0">
          <h1 className="text-lg font-medium text-base-content/90">Content intake</h1>
          <p className="text-[13px] text-base-content/40 mt-0.5">
            Pick posts and add extras for "{(issue as any)?.title}". The AI will curate and arrange them.
          </p>
          <div className="text-[11px] text-base-content/30 mt-2 leading-relaxed max-w-2xl">
            <strong className="text-base-content/50">How it works:</strong> Browse your posts on the <strong>left</strong> — check the ones you want and click "Add to issue".
            They appear in the <strong>tray on the right</strong> where you can set importance (must/should/could) and role (cover, feature, etc.).
            You can also add extra text, images, or quotes with the "Add text" button. You need at least <strong>5 items</strong> with at least <strong>1 marked "must"</strong> to continue.
          </div>
          {addError && <div className="alert alert-error text-[11px] mt-2 py-1 px-3">{addError}</div>}
        </div>

        {/* Two-pane layout */}
        <div className="flex flex-1 overflow-hidden">
          {/* LEFT — Post picker */}
          <div className="w-1/2 border-r border-base-300/20 flex flex-col">
            <div className="p-3 border-b border-base-300/10 space-y-2 shrink-0">
              <label className="input input-bordered input-sm flex items-center gap-2 text-[12px]">
                <Search className="h-3.5 w-3.5 text-base-content/30" />
                <input type="text" value={search} onChange={e => setSearch(e.target.value)}
                  placeholder="Search posts..." className="grow bg-transparent" />
              </label>
              <div className="flex gap-2">
                <select value={categoryFilter} onChange={e => setCategoryFilter(e.target.value)}
                  className="select select-bordered select-xs text-[11px] flex-1">
                  <option value="">All categories</option>
                  {(categories || []).map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                {selectedPostIds.size > 0 && (
                  <button onClick={handleAddSelected} disabled={addItemsMutation.isPending}
                    className="btn btn-primary btn-xs text-[11px] gap-1">
                    {addItemsMutation.isPending ? <Loader2 size={11} className="animate-spin" /> : <Plus size={11} />}
                    Add {selectedPostIds.size} to issue
                  </button>
                )}
              </div>
            </div>

            <div className="flex-1 overflow-y-auto">
              {postsLoading && <div className="flex justify-center py-10"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>}
              {(postsList || []).map((post: PostItem) => {
                const inTray = trayPostIds.has(post.id);
                const isSelected = selectedPostIds.has(post.id);
                return (
                  <div key={post.id}
                    className={`flex items-start gap-3 px-4 py-3 border-b border-base-300/10 cursor-pointer transition-colors ${
                      inTray ? 'bg-success/5 opacity-60' : isSelected ? 'bg-primary/5' : 'hover:bg-base-300/10'
                    }`}
                    onClick={() => !inTray && togglePost(post.id)}>
                    <input type="checkbox" checked={isSelected} disabled={inTray} readOnly
                      className="checkbox checkbox-xs checkbox-primary mt-1" />
                    <div className="flex-1 min-w-0">
                      <div className="text-[13px] font-medium text-base-content/80 truncate">{post.title}</div>
                      {post.excerpt && <p className="text-[11px] text-base-content/40 mt-0.5 line-clamp-2">{post.excerpt}</p>}
                      <div className="flex items-center gap-2 mt-1 text-[10px] text-base-content/30">
                        {post.category && <span className="badge badge-ghost badge-xs">{post.category.name}</span>}
                        {post.published_at && <span>{new Date(post.published_at).toLocaleDateString()}</span>}
                        {inTray && <span className="text-success font-medium">In tray</span>}
                      </div>
                    </div>
                  </div>
                );
              })}
              {!postsLoading && (postsList || []).length === 0 && (
                <div className="text-center py-10 text-[12px] text-base-content/25">No posts found</div>
              )}
            </div>
          </div>

          {/* RIGHT — Issue tray */}
          <div className="w-1/2 flex flex-col">
            <div className="p-3 border-b border-base-300/10 flex items-center justify-between shrink-0">
              <div className="text-[12px] text-base-content/50">
                <span className="font-medium text-base-content/70">{items.length} items</span>
                {' · '}{postCount} posts · {extraCount} extras · ~{totalWords.toLocaleString()} words
              </div>
              <button onClick={() => setShowExtraModal(true)} className="btn btn-ghost btn-xs text-[11px] gap-1">
                <Type size={11} /> Add text
              </button>
            </div>

            <div className="flex-1 overflow-y-auto">
              {items.length === 0 && (
                <div className="text-center py-16 text-base-content/20">
                  <FileText size={32} className="mx-auto mb-3 opacity-30" />
                  <p className="text-[13px]">Pick posts on the left</p>
                  <p className="text-[11px] mt-1">Or add extras (text, images) below</p>
                </div>
              )}

              {items.map((item: ContentItem) => (
                <div key={item.id} className="px-4 py-3 border-b border-base-300/10">
                  <div className="flex items-start gap-2">
                    <GripVertical size={12} className="text-base-content/15 mt-1 shrink-0 cursor-grab" />
                    <div className="flex-1 min-w-0">
                      {/* Title */}
                      <div className="flex items-center gap-2">
                        {item.source_type === 'post' && <FileText size={11} className="text-base-content/30" />}
                        {item.source_type === 'extra_text' && <Type size={11} className="text-primary/50" />}
                        {item.source_type === 'extra_image' && <ImageIcon size={11} className="text-success/50" />}
                        {item.source_type === 'extra_video' && <Film size={11} className="text-info/50" />}
                        <span className="text-[12px] font-medium text-base-content/70 truncate">
                          {item.source_type === 'post' ? ((item as any).post_title || `Post: ${item.source_id?.slice(0, 8)}`) :
                           item.source_type === 'extra_text' ? (item.extra_payload as any)?.caption || 'Extra text' :
                           item.source_type === 'extra_image' ? 'Image' :
                           item.source_type === 'extra_video' ? 'Video' :
                           item.source_type}
                        </span>
                        <button onClick={() => removeItemMutation.mutate(item.id)}
                          className="btn btn-ghost btn-xs btn-square text-base-content/20 hover:text-error ml-auto shrink-0">
                          <Trash2 size={11} />
                        </button>
                      </div>

                      {/* Controls */}
                      <div className="flex items-center gap-2 mt-2">
                        {/* Importance */}
                        <div className="flex bg-base-200/80 rounded p-0.5">
                          {['must', 'should', 'could'].map(imp => (
                            <button key={imp}
                              onClick={() => updateItemMutation.mutate({ itemId: item.id, data: { importance: imp } })}
                              className={`px-2 py-0.5 rounded text-[10px] font-medium transition-colors ${
                                item.importance === imp
                                  ? imp === 'must' ? 'bg-error/20 text-error' : imp === 'should' ? 'bg-warning/20 text-warning' : 'bg-base-300/30 text-base-content/50'
                                  : 'text-base-content/30 hover:text-base-content/50'
                              }`}>
                              {imp}
                            </button>
                          ))}
                        </div>

                        {/* Role hint */}
                        <select value={item.role_hint}
                          onChange={e => updateItemMutation.mutate({ itemId: item.id, data: { role_hint: e.target.value } })}
                          className="select select-bordered select-xs text-[10px] h-6 min-h-0">
                          <option value="none">No preference</option>
                          <option value="cover">Cover</option>
                          <option value="feature">Feature</option>
                          <option value="short">Short</option>
                          <option value="visual_break">Visual break</option>
                          <option value="closing">Closing</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {/* Bottom bar — summary + advance */}
            <div className="p-3 border-t border-base-300/20 bg-base-100 shrink-0">
              <div className="flex items-center justify-between">
                <div className="text-[11px] text-base-content/40">
                  {items.length} items · {mustCount} must-have
                  {!canAdvance && items.length < 5 && <span className="text-warning ml-2">Need at least 5 items</span>}
                  {!canAdvance && mustCount < 1 && items.length >= 5 && <span className="text-warning ml-2">Mark at least 1 as "must"</span>}
                </div>
                <div className="flex gap-2">
                  <button onClick={() => navigate(`/sites/${siteId}/issue-composer/${issueId}`)}
                    className="btn btn-ghost btn-sm text-[12px]">← Back to brief</button>
                  <button onClick={() => advanceMutation.mutate()} disabled={!canAdvance || advanceMutation.isPending}
                    className="btn btn-primary btn-sm text-[12px] gap-1">
                    {advanceMutation.isPending && <Loader2 size={12} className="animate-spin" />}
                    Next: Curation & flow →
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Extra text modal */}
        {showExtraModal && (
          <dialog className="modal modal-open">
            <div className="modal-box bg-base-100 max-w-md">
              <h3 className="text-sm font-medium text-base-content/80 mb-3">Add extra text</h3>
              <div className="space-y-3">
                <div>
                  <label className="text-[11px] text-base-content/50 mb-1 block">Caption / label</label>
                  <input value={extraCaption} onChange={e => setExtraCaption(e.target.value)}
                    className="input input-bordered input-sm w-full text-[12px]" placeholder="e.g. Editor's note, Pull quote" />
                </div>
                <div>
                  <label className="text-[11px] text-base-content/50 mb-1 block">Text content</label>
                  <textarea value={extraText} onChange={e => setExtraText(e.target.value)} rows={5}
                    className="textarea textarea-bordered textarea-sm w-full text-[12px]"
                    placeholder="Paste or type your text here..." />
                </div>
              </div>
              <div className="modal-action">
                <button onClick={() => setShowExtraModal(false)} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
                <button onClick={handleAddExtra} disabled={!extraText.trim() || addExtraMutation.isPending}
                  className="btn btn-primary btn-sm text-[12px]">Add to tray</button>
              </div>
            </div>
            <form method="dialog" className="modal-backdrop"><button onClick={() => setShowExtraModal(false)}>close</button></form>
          </dialog>
        )}
      </div>
    </IssueComposerLayout>
  );
}
