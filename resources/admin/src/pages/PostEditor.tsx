import { useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, LayoutList, Paintbrush, Eye,
  Calendar, FolderTree, Clock, History, Globe, ChevronDown,
} from 'lucide-react';
import { usePostData } from '@/hooks/usePageData';
import { useAutoSave } from '@/hooks/useAutoSave';
import { useEditorShortcuts } from '@/hooks/useEditorShortcuts';
import { useThemeFonts } from '@/hooks/useThemeFonts';
import { useEditorStore } from '@/stores/editorStore';
import { BuilderCanvas, BuilderDndProvider } from '@/components/editor/BuilderCanvas';
import { MagazineEditorCanvas } from '@/components/editor/MagazineEditorCanvas';
import { BlockSettings } from '@/components/editor/BlockSettings';
import { LayersPanel } from '@/components/editor/LayersPanel';
import { BlockPicker } from '@/components/editor/BlockPicker';
import { api, blocks as blocksApi, posts as postsApi, categories as categoriesApi, versions as versionsApi, publishing, sites } from '@/lib/api';
import { AssetField } from '@/components/ui/AssetPicker';
import WysiwygEditor from '@/components/editor/WysiwygEditor';
import { slugify } from '@/lib/slugify';

import '@/components/blocks';

type EditorMode = 'simple' | 'block' | 'magazine';
type RightTab = 'settings' | 'post' | 'layers' | 'blocks';

export default function PostEditor() {
  const { siteId = '', postId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { post, blocks: fetchedBlocks, isLoading, error } = usePostData(siteId, postId);
  const setBlocks = useEditorStore((s) => s.setBlocks);
  const editorBlocks = useEditorStore((s) => s.blocks);
  const isDirty = useEditorStore((s) => s.isDirty);
  const isSaving = useEditorStore((s) => s.isSaving);
  const setSaving = useEditorStore((s) => s.setSaving);
  const setDirty = useEditorStore((s) => s.setDirty);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const setStoreEditorMode = useEditorStore((s) => s.setEditorMode);

  const [editorMode, setEditorMode] = useState<EditorMode>('simple');
  const [simpleContent, setSimpleContent] = useState('');
  const [rightTab, setRightTab] = useState<RightTab>('post');
  const [mobilePanelOpen, setMobilePanelOpen] = useState(false);
  const [saveError, setSaveError] = useState('');

  // Post metadata
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [status, setStatus] = useState('draft');
  const [categoryId, setCategoryId] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [featuredImage, setFeaturedImage] = useState('');
  const [videoUrl, setVideoUrl] = useState('');
  const [thumbnail, setThumbnail] = useState('');
  const [postFormat, setPostFormat] = useState('standard');
  const [publishedAt, setPublishedAt] = useState('');
  const [scheduledAt, setScheduledAt] = useState('');
  const [layoutId, setLayoutId] = useState('');
  const [metaDirty, setMetaDirty] = useState(false);
  const [slugManual, setSlugManual] = useState(false);

  // Site domain for View links
  const { data: siteData } = useQuery<any>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then((r: any) => r.data.data),
  });
  const publicBase = siteData?.custom_domain ? `https://${siteData.custom_domain}` : `https://${siteData?.slug || ''}.ensodo.eu`;

  // Layouts
  const { data: layoutsList } = useQuery<any[]>({
    queryKey: ['layouts', siteId],
    queryFn: () => api.get(`/sites/${siteId}/layouts`).then((r: any) => {
      const d = r.data?.data;
      return Array.isArray(d) ? d : [];
    }),
  });

  // Categories & tags
  const { data: categoriesList } = useQuery({
    queryKey: ['categories', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data),
  });

  // Versions
  const { data: versionsList } = useQuery({
    queryKey: ['versions', siteId, postId],
    queryFn: () => versionsApi.listForPost(siteId, postId).then((r: any) => r.data.data),
    enabled: !!postId,
  });

  useAutoSave(siteId, 'posts', postId);
  useEditorShortcuts(siteId, 'posts', postId);
  useThemeFonts(siteId);

  // Load blocks only on initial fetch — never overwrite after user starts editing
  const blocksLoadedRef = useRef(false);
  useEffect(() => {
    if (fetchedBlocks && !blocksLoadedRef.current) {
      setBlocks(fetchedBlocks);
      blocksLoadedRef.current = true;
      // Extract simple content from first text/rich-text block
      const textBlock = fetchedBlocks.find((b: any) => b.type === 'text' || b.type === 'rich-text');
      if (textBlock?.data?.content) {
        setSimpleContent(textBlock.data.content as string);
      }
    }
  }, [fetchedBlocks, setBlocks]);

  const initializedPost = useRef(false);
  useEffect(() => {
    if (!post) return;
    // Only initialize from server data on first load, not on refetches
    // (refetches after save would overwrite unsaved local changes)
    if (initializedPost.current && metaDirty) return;
    initializedPost.current = true;

    setTitle(post.title || '');
    setSlug(post.slug || '');
    setStatus(post.status || 'draft');
    setCategoryId(post.category?.id || post.category_id || '');
    setLayoutId(post.layout_id || '');
    setExcerpt(post.excerpt || '');
    setFeaturedImage(post.featured_image || '');
    setVideoUrl(post.video_url || '');
    setThumbnail(post.thumbnail || '');
    setPostFormat(post.post_format || 'standard');
    setPublishedAt(post.published_at ? new Date(post.published_at).toISOString().slice(0, 16) : '');
    setScheduledAt(post.scheduled_at ? new Date(post.scheduled_at).toISOString().slice(0, 16) : '');
    setSlugManual(!!post.slug);
    if (post.editor_mode === 'simple' || post.editor_mode === 'block' || post.editor_mode === 'magazine') {
      setEditorMode(post.editor_mode as EditorMode);
      setStoreEditorMode(post.editor_mode as EditorMode);
    }
  }, [post]);

  useEffect(() => {
    if (selectedBlockId) setRightTab('settings');
  }, [selectedBlockId]);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (isDirty || metaDirty) e.preventDefault(); };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty, metaDirty]);

  // Save — always saves metadata + blocks (keeps current status)
  async function handleSave() {
    const previousStatus = post?.status;
    setSaving(true);
    setSaveError('');
    try {
      // Always save metadata to ensure category, title, etc. persist
      await postsApi.update(siteId, postId, {
        title, slug, status, category_id: categoryId || null, layout_id: layoutId || null,
        excerpt: excerpt || null, featured_image: featuredImage || null,
        video_url: videoUrl || null, thumbnail: thumbnail || null, post_format: postFormat,
        editor_mode: editorMode,
        published_at: publishedAt || null, scheduled_at: scheduledAt || null,
      });
      setMetaDirty(false);
      // Save blocks — in simple mode, wrap content in a single text block
      if (editorMode === 'simple') {
        // Find existing text/rich-text block to preserve its ID, keep all other blocks
        const existingText = editorBlocks.find((b: any) => b.type === 'text' || b.type === 'rich-text');
        const otherBlocks = editorBlocks.filter((b: any) => b.type !== 'text' && b.type !== 'rich-text');
        const textBlock = {
          ...(existingText || {}),
          id: existingText?.id || undefined,
          type: 'text',
          level: 'module',
          data: { content: simpleContent },
          style: existingText?.style || {},
          order: 0,
          children: existingText?.children || [],
        };
        const simpleBlocks = [textBlock, ...otherBlocks.map((b: any, i: number) => ({ ...b, order: i + 1 }))];
        await blocksApi.sync(siteId, 'posts', postId, simpleBlocks);
      } else {
        await blocksApi.sync(siteId, 'posts', postId, editorBlocks);
      }
      setDirty(false);
      queryClient.invalidateQueries({ queryKey: ['post', siteId, postId] });
      // If status changed (e.g. published→draft), trigger republish so front page
      // and static files update (draft posts get removed from public site)
      if (previousStatus && previousStatus !== status) {
        publishing.publish(siteId).catch(() => {});
      }
    } catch (err: any) {
      const msg = err.response?.data?.message || (err.response?.data?.errors ? JSON.stringify(err.response.data.errors) : err.message);
      setSaveError(msg);
      console.error('Save failed:', err.response?.data || err);
    } finally { setSaving(false); }
  }

  // Publish — saves all metadata + blocks, sets status to published, triggers deploy
  async function handlePublish() {
    setSaving(true);
    setSaveError('');
    try {
      // Set published and save all metadata
      const pubStatus = 'published';
      const pubDate = publishedAt || new Date().toISOString();
      await postsApi.update(siteId, postId, {
        title, slug, status: pubStatus, category_id: categoryId || null, layout_id: layoutId || null,
        excerpt: excerpt || null, featured_image: featuredImage || null,
        editor_mode: editorMode,
        published_at: pubDate, scheduled_at: scheduledAt || null,
      });
      // Save blocks
      if (editorMode === 'simple') {
        const existingText2 = editorBlocks.find((b: any) => b.type === 'text' || b.type === 'rich-text');
        const otherBlocks2 = editorBlocks.filter((b: any) => b.type !== 'text' && b.type !== 'rich-text');
        await blocksApi.sync(siteId, 'posts', postId, [
          { ...(existingText2 || {}), id: existingText2?.id, type: 'text', level: 'module', data: { content: simpleContent }, style: existingText2?.style || {}, order: 0, children: existingText2?.children || [] },
          ...otherBlocks2.map((b: any, i: number) => ({ ...b, order: i + 1 })),
        ]);
      } else {
        await blocksApi.sync(siteId, 'posts', postId, editorBlocks);
      }
      // Update local state to match what was saved
      setStatus(pubStatus);
      setPublishedAt(new Date(pubDate).toISOString().slice(0, 16));
      setDirty(false);
      setMetaDirty(false);
      queryClient.invalidateQueries({ queryKey: ['post', siteId, postId] });
      // Trigger publish in background
      publishing.publish(siteId).catch(() => {});
    } catch (err: any) {
      setSaveError(err.response?.data?.message || err.message);
    } finally { setSaving(false); }
  }

  function switchEditorMode(mode: EditorMode) {
    setEditorMode(mode);
    setStoreEditorMode(mode);
    setMetaDirty(true);
  }

  function markMetaDirty() { setMetaDirty(true); }

  const canSave = isDirty || metaDirty;

  if (isLoading) {
    return <div className="flex items-center justify-center h-screen bg-base-200"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>;
  }
  if (error) {
    return <div className="flex items-center justify-center h-screen bg-base-200 text-error text-[13px]">Failed to load post</div>;
  }

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  return (
    <div className="flex flex-col h-screen bg-base-200" data-theme={adminTheme}>
      {/* ─── Top toolbar ─── */}
      <div className="flex items-center justify-between h-12 px-4 bg-base-100 border-b border-base-300/30 shrink-0">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/posts`)} className="btn btn-ghost btn-xs btn-square"><ArrowLeft size={16} /></button>
          <input value={title} onChange={e => { setTitle(e.target.value); if (!slugManual) setSlug(slugify(e.target.value)); markMetaDirty(); }}
            className="text-sm font-medium bg-transparent border-none outline-none text-base-content/90 w-48" placeholder="Post title" />
          <span className={`badge badge-sm ${status === 'published' ? 'badge-success' : 'badge-ghost'} badge-outline text-[10px]`}>{status}</span>
          {(isDirty || metaDirty) && <span className="text-[10px] text-warning font-medium">unsaved</span>}
          {saveError && <span className="text-[10px] text-error truncate max-w-xs">{saveError}</span>}
        </div>

        <div className="flex items-center gap-2">
          {/* Editor mode toggle */}
          <div className="flex bg-base-200/80 rounded-md p-0.5">
            <button onClick={() => switchEditorMode('simple')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'simple' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              Simple
            </button>
            <button onClick={() => switchEditorMode('block')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'block' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              <LayoutList size={12} /> Blocks
            </button>
            <button onClick={() => switchEditorMode('magazine')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'magazine' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              <Paintbrush size={12} /> Canvas
            </button>
          </div>
          <div className="w-px h-5 bg-base-300/30" />
          <a href={(() => {
              const cat = categoriesList?.find((c: any) => c.id === categoryId);
              return `${publicBase}/${cat?.slug ? cat.slug + '/' : ''}${slug}`;
            })()}
            target="_blank" rel="noopener"
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="View post">
            <Eye size={13} /> View
          </a>
          <button onClick={handleSave} disabled={isSaving}
            className={`btn btn-sm text-[12px] gap-1 ${canSave ? 'btn-warning' : 'btn-ghost text-base-content/30'}`}>
            {isSaving ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />}
            Save{status !== 'published' ? ' Draft' : ''}
            {canSave && <span className="w-1.5 h-1.5 rounded-full bg-warning-content" />}
          </button>
          <button onClick={handlePublish} disabled={isSaving}
            className={`btn btn-sm text-[12px] gap-1 ${canSave ? 'btn-primary animate-pulse' : 'btn-primary'}`}>
            <Globe size={13} /> {status === 'published' ? 'Update & Publish' : 'Publish'}
          </button>
        </div>
      </div>

      {/* ─── Editor body ─── */}
      <div className="flex flex-1 overflow-hidden">
        {editorMode === 'simple' ? (
          /* Simple WYSIWYG editor — full screen, classic WordPress-like */
          <div className="flex flex-1 overflow-x-auto overflow-y-hidden lg:overflow-x-hidden snap-x snap-mandatory">
            <div className="w-full min-w-full lg:min-w-0 lg:flex-1 snap-start overflow-y-auto p-4 lg:p-8">
              <div className="max-w-3xl mx-auto">
                <WysiwygEditor
                  content={simpleContent}
                  onChange={(html) => { setSimpleContent(html); setDirty(true); }}
                  placeholder="Start writing your post..."
                  minHeight={500}
                />
              </div>
            </div>
            {/* Post settings sidebar — swipe to reach */}
            <div className="w-80 min-w-[320px] bg-base-100 border-l border-base-300/30 flex flex-col shrink-0 snap-start">
              <div className="p-1 border-b border-base-300/20 text-center text-[10px] text-base-content/30 font-medium">Post Settings</div>
              <div className="flex-1 overflow-y-auto">
                <PostMetaPanel
                  slug={slug} setSlug={s => { setSlug(s); markMetaDirty(); }}
                  slugManual={slugManual} setSlugManual={setSlugManual}
                  title={title}
                  status={status} setStatus={s => { setStatus(s); markMetaDirty(); }}
                  categoryId={categoryId} setCategoryId={s => { setCategoryId(s); markMetaDirty(); }}
                  excerpt={excerpt} setExcerpt={s => { setExcerpt(s); markMetaDirty(); }}
                  featuredImage={featuredImage} setFeaturedImage={s => { setFeaturedImage(s); markMetaDirty(); }}
                  videoUrl={videoUrl} setVideoUrl={s => { setVideoUrl(s); markMetaDirty(); }}
                  thumbnail={thumbnail} setThumbnail={s => { setThumbnail(s); markMetaDirty(); }}
                  postFormat={postFormat} setPostFormat={s => { setPostFormat(s); markMetaDirty(); }}
                  publishedAt={publishedAt} setPublishedAt={s => { setPublishedAt(s); markMetaDirty(); }}
                  scheduledAt={scheduledAt} setScheduledAt={s => { setScheduledAt(s); markMetaDirty(); }}
                  layoutId={layoutId} setLayoutId={s => { setLayoutId(s); markMetaDirty(); }}
                  layouts={layoutsList || []}
                  categories={categoriesList || []}
                  versions={versionsList || []}
                  siteId={siteId} postId={postId}
                />
              </div>
            </div>
          </div>
        ) : editorMode === 'block' ? (
          <BuilderDndProvider>
            {/* Mobile: floating + button opens block picker popup */}
            <div className="lg:hidden">
              <button
                type="button"
                onClick={() => setMobilePanelOpen(!mobilePanelOpen)}
                className="fixed bottom-4 right-4 z-50 w-14 h-14 rounded-full bg-primary text-primary-content shadow-xl flex items-center justify-center text-2xl"
              >
                {mobilePanelOpen ? '✕' : '+'}
              </button>

              {/* Mobile popup panel */}
              {mobilePanelOpen && (
                <div className="fixed inset-0 z-40 flex items-end justify-center" onClick={() => setMobilePanelOpen(false)}>
                  <div className="absolute inset-0 bg-black/30" />
                  <div className="relative bg-base-100 rounded-t-2xl shadow-2xl w-full max-h-[80vh] flex flex-col" onClick={e => e.stopPropagation()}>
                    <div className="flex items-center justify-between px-4 py-3 border-b border-base-300/20">
                      <div className="flex gap-2">
                        {([
                          { key: 'blocks' as RightTab, label: '+ Add Block' },
                          { key: 'settings' as RightTab, label: 'Block Settings' },
                          { key: 'post' as RightTab, label: 'Post' },
                        ]).map(tab => (
                          <button key={tab.key} onClick={() => setRightTab(tab.key)}
                            className={`px-3 py-1 text-[12px] font-medium rounded-full ${rightTab === tab.key ? 'bg-primary text-primary-content' : 'text-base-content/50'}`}>
                            {tab.label}
                          </button>
                        ))}
                      </div>
                      <button onClick={() => setMobilePanelOpen(false)} className="text-base-content/40 text-xl px-2">✕</button>
                    </div>
                    <div className="flex-1 overflow-y-auto p-2">
                      {rightTab === 'blocks' && <BlockPicker />}
                      {rightTab === 'settings' && <BlockSettings />}
                      {rightTab === 'post' && (
                        <PostMetaPanel
                          slug={slug} setSlug={s => { setSlug(s); markMetaDirty(); }}
                          slugManual={slugManual} setSlugManual={setSlugManual}
                          title={title}
                          status={status} setStatus={s => { setStatus(s); markMetaDirty(); }}
                          categoryId={categoryId} setCategoryId={s => { setCategoryId(s); markMetaDirty(); }}
                          excerpt={excerpt} setExcerpt={s => { setExcerpt(s); markMetaDirty(); }}
                          featuredImage={featuredImage} setFeaturedImage={s => { setFeaturedImage(s); markMetaDirty(); }}
                          videoUrl={videoUrl} setVideoUrl={s => { setVideoUrl(s); markMetaDirty(); }}
                          thumbnail={thumbnail} setThumbnail={s => { setThumbnail(s); markMetaDirty(); }}
                          postFormat={postFormat} setPostFormat={s => { setPostFormat(s); markMetaDirty(); }}
                          publishedAt={publishedAt} setPublishedAt={s => { setPublishedAt(s); markMetaDirty(); }}
                          scheduledAt={scheduledAt} setScheduledAt={s => { setScheduledAt(s); markMetaDirty(); }}
                          layoutId={layoutId} setLayoutId={s => { setLayoutId(s); markMetaDirty(); }}
                          layouts={layoutsList || []}
                          categories={categoriesList || []}
                          versions={versionsList || []}
                          siteId={siteId} postId={postId}
                        />
                      )}
                    </div>
                  </div>
                </div>
              )}
            </div>

            <div className="flex flex-1 overflow-hidden">
            <div className="flex-1 overflow-y-auto">
              <BuilderCanvas />
            </div>
            {/* Desktop sidebar — always visible on lg+ */}
            <div className="hidden lg:flex w-80 min-w-[320px] bg-base-100 border-l border-base-300/30 flex-col shrink-0">
              <div className="flex border-b border-base-300/20 shrink-0">
                {([
                  { key: 'post' as RightTab, label: 'Post' },
                  { key: 'settings' as RightTab, label: 'Block' },
                  { key: 'blocks' as RightTab, label: '+ Add' },
                ]).map(tab => (
                  <button key={tab.key} onClick={() => setRightTab(tab.key)}
                    className={`flex-1 px-2 py-2 text-[11px] font-medium transition-colors ${rightTab === tab.key ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
                    {tab.label}
                  </button>
                ))}
              </div>
              <div className="flex-1 overflow-y-auto">
                {rightTab === 'settings' && <BlockSettings />}
                {rightTab === 'blocks' && <div className="h-full"><BlockPicker /></div>}
                {rightTab === 'post' && (
                  <PostMetaPanel
                    slug={slug} setSlug={s => { setSlug(s); markMetaDirty(); }}
                    slugManual={slugManual} setSlugManual={setSlugManual}
                    title={title}
                    status={status} setStatus={s => { setStatus(s); markMetaDirty(); }}
                    categoryId={categoryId} setCategoryId={s => { setCategoryId(s); markMetaDirty(); }}
                    excerpt={excerpt} setExcerpt={s => { setExcerpt(s); markMetaDirty(); }}
                    featuredImage={featuredImage} setFeaturedImage={s => { setFeaturedImage(s); markMetaDirty(); }}
                    videoUrl={videoUrl} setVideoUrl={s => { setVideoUrl(s); markMetaDirty(); }}
                    thumbnail={thumbnail} setThumbnail={s => { setThumbnail(s); markMetaDirty(); }}
                    postFormat={postFormat} setPostFormat={s => { setPostFormat(s); markMetaDirty(); }}
                    publishedAt={publishedAt} setPublishedAt={s => { setPublishedAt(s); markMetaDirty(); }}
                    scheduledAt={scheduledAt} setScheduledAt={s => { setScheduledAt(s); markMetaDirty(); }}
                    layoutId={layoutId} setLayoutId={s => { setLayoutId(s); markMetaDirty(); }}
                    layouts={layoutsList || []}
                    categories={categoriesList || []}
                    versions={versionsList || []}
                    siteId={siteId} postId={postId}
                  />
                )}
              </div>
            </div>
            </div>{/* close flex container */}
          </BuilderDndProvider>
        ) : (
          <>
            <MagazineEditorCanvas />
            <div className="w-80 bg-base-100 border-l border-base-300/30 flex flex-col shrink-0">
              <div className="flex border-b border-base-300/20 shrink-0">
                {([
                  { key: 'post' as RightTab, label: 'Post' },
                  { key: 'settings' as RightTab, label: 'Block' },
                  { key: 'layers' as RightTab, label: 'Layers' },
                  { key: 'blocks' as RightTab, label: '+ Add' },
                ]).map(tab => (
                  <button key={tab.key} onClick={() => setRightTab(tab.key)}
                    className={`flex-1 px-2 py-2 text-[11px] font-medium transition-colors ${rightTab === tab.key ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
                    {tab.label}
                  </button>
                ))}
              </div>
              <div className="flex-1 overflow-y-auto">
                {rightTab === 'settings' && <BlockSettings />}
                {rightTab === 'layers' && <LayersPanel />}
                {rightTab === 'blocks' && <div className="h-full"><BlockPicker /></div>}
                {rightTab === 'post' && (
                  <PostMetaPanel
                    slug={slug} setSlug={s => { setSlug(s); markMetaDirty(); }}
                    slugManual={slugManual} setSlugManual={setSlugManual}
                    title={title}
                    status={status} setStatus={s => { setStatus(s); markMetaDirty(); }}
                    categoryId={categoryId} setCategoryId={s => { setCategoryId(s); markMetaDirty(); }}
                    excerpt={excerpt} setExcerpt={s => { setExcerpt(s); markMetaDirty(); }}
                    featuredImage={featuredImage} setFeaturedImage={s => { setFeaturedImage(s); markMetaDirty(); }}
                    videoUrl={videoUrl} setVideoUrl={s => { setVideoUrl(s); markMetaDirty(); }}
                    thumbnail={thumbnail} setThumbnail={s => { setThumbnail(s); markMetaDirty(); }}
                    postFormat={postFormat} setPostFormat={s => { setPostFormat(s); markMetaDirty(); }}
                    publishedAt={publishedAt} setPublishedAt={s => { setPublishedAt(s); markMetaDirty(); }}
                    scheduledAt={scheduledAt} setScheduledAt={s => { setScheduledAt(s); markMetaDirty(); }}
                    layoutId={layoutId} setLayoutId={s => { setLayoutId(s); markMetaDirty(); }}
                    layouts={layoutsList || []}
                    categories={categoriesList || []}
                    versions={versionsList || []}
                    siteId={siteId} postId={postId}
                  />
                )}
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════
// Post Metadata Panel
// ═══════════════════════════════════════════
function PostMetaPanel({ slug, setSlug, slugManual, setSlugManual, title, status, setStatus, categoryId, setCategoryId, layoutId, setLayoutId, layouts, excerpt, setExcerpt, featuredImage, setFeaturedImage, videoUrl, setVideoUrl, thumbnail, setThumbnail, postFormat, setPostFormat, publishedAt, setPublishedAt, scheduledAt, setScheduledAt, categories, versions, siteId, postId }: {
  slug: string; setSlug: (v: string) => void;
  slugManual: boolean; setSlugManual: (v: boolean) => void;
  title: string;
  status: string; setStatus: (v: string) => void;
  categoryId: string; setCategoryId: (v: string) => void;
  layoutId: string; setLayoutId: (v: string) => void;
  layouts: Array<{ id: string; name: string; slug: string; is_system: boolean; description?: string }>;
  excerpt: string; setExcerpt: (v: string) => void;
  featuredImage: string; setFeaturedImage: (v: string) => void;
  videoUrl: string; setVideoUrl: (v: string) => void;
  thumbnail: string; setThumbnail: (v: string) => void;
  postFormat: string; setPostFormat: (v: string) => void;
  publishedAt: string; setPublishedAt: (v: string) => void;
  scheduledAt: string; setScheduledAt: (v: string) => void;
  categories: Array<{ id: string; name: string }>;
  versions: Array<{ id: string; created_at: string }>;
  siteId: string; postId: string;
}) {
  const [showVersions, setShowVersions] = useState(false);

  return (
    <div className="p-3 space-y-4">
      {/* Status */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block flex items-center gap-1"><Eye size={11} /> Status</label>
        <select value={status} onChange={e => setStatus(e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]">
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      {/* Slug */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block flex items-center gap-1"><Globe size={11} /> URL slug</label>
        <div className="flex gap-1">
          <input value={slug} onChange={e => { setSlug(slugify(e.target.value)); setSlugManual(true); }}
            className="input input-bordered input-sm w-full text-[12px] font-mono" placeholder="post-url-slug" />
          {slugManual && (
            <button onClick={() => { setSlugManual(false); setSlug(slugify(title)); }}
              className="btn btn-ghost btn-sm btn-square text-[10px]" title="Auto-generate from title">
              <History size={12} />
            </button>
          )}
        </div>
        <p className="text-[10px] text-base-content/25 mt-0.5">/blog/{slug}</p>
      </div>

      {/* Category */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block flex items-center gap-1"><FolderTree size={11} /> Category</label>
        <select value={categoryId} onChange={e => setCategoryId(e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]">
          <option value="">No category</option>
          {categories.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>

      {/* Layout */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block flex items-center gap-1">Layout</label>
        <select value={layoutId} onChange={e => setLayoutId(e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]">
          <option value="">Inherit (Standard)</option>
          {layouts.map((l: any) => (
            <option key={l.id} value={l.id}>
              {l.name} {l.is_system ? '' : '(custom)'}
            </option>
          ))}
        </select>
        {layoutId && (
          <button onClick={() => setLayoutId('')} className="text-[10px] text-primary mt-0.5">Reset to inherited</button>
        )}
      </div>

      {/* Excerpt */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block">Excerpt</label>
        <textarea value={excerpt} onChange={e => setExcerpt(e.target.value)} rows={3}
          className="textarea textarea-bordered textarea-sm w-full text-[12px]" placeholder="Short description for previews and SEO..." />
        <p className="text-[10px] text-base-content/25 mt-0.5">{excerpt.length}/300 characters</p>
      </div>

      {/* Post Format */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block">Post Format</label>
        <select value={postFormat} onChange={e => setPostFormat(e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]">
          <option value="standard">Standard</option>
          <option value="video">Video</option>
          <option value="gallery">Gallery</option>
          <option value="audio">Audio</option>
          <option value="link">Link</option>
        </select>
      </div>

      {/* Featured image */}
      <AssetField label="Featured image" value={featuredImage} onChange={(url) => setFeaturedImage(url)} accept="image" />

      {/* Thumbnail (separate from featured image) */}
      <AssetField label="Thumbnail" value={thumbnail} onChange={(url) => setThumbnail(url)} accept="image" />
      <p className="text-[10px] text-base-content/25 -mt-3">Optional smaller image for cards and lists</p>

      {/* Video URL */}
      {(postFormat === 'video' || videoUrl) && (
        <div>
          <label className="text-[11px] text-base-content/40 mb-1 block">Video URL</label>
          <input type="url" value={videoUrl} onChange={e => setVideoUrl(e.target.value)}
            className="input input-bordered input-sm w-full text-[12px]"
            placeholder="https://youtube.com/watch?v=... or https://vimeo.com/..." />
          <p className="text-[10px] text-base-content/25 mt-0.5">YouTube, Vimeo, or direct video URL</p>
        </div>
      )}

      {/* Published date */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block flex items-center gap-1"><Calendar size={11} /> Published date</label>
        <input type="datetime-local" value={publishedAt} onChange={e => setPublishedAt(e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
      </div>

      {/* Scheduled publishing */}
      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block flex items-center gap-1"><Clock size={11} /> Schedule publish</label>
        <input type="datetime-local" value={scheduledAt} onChange={e => setScheduledAt(e.target.value)}
          className="input input-bordered input-sm w-full text-[12px]" />
        <p className="text-[10px] text-base-content/25 mt-0.5">Leave empty for manual publishing</p>
      </div>

      {/* Revisions */}
      <div className="border-t border-base-300/20 pt-3">
        <button onClick={() => setShowVersions(!showVersions)}
          className="flex items-center justify-between w-full text-[11px] text-base-content/50 hover:text-base-content/70">
          <span className="flex items-center gap-1"><History size={11} /> Revisions ({versions?.length || 0})</span>
          <ChevronDown size={11} className={`transition-transform ${showVersions ? 'rotate-180' : ''}`} />
        </button>
        {showVersions && versions && versions.length > 0 && (
          <div className="mt-2 space-y-1 max-h-40 overflow-y-auto">
            {versions.map((v: any) => (
              <div key={v.id} className="flex items-center justify-between text-[11px] py-1 border-b border-base-300/10">
                <span className="text-base-content/50">{new Date(v.created_at).toLocaleString()}</span>
                <button onClick={async () => {
                  if (confirm('Restore this version? Current content will be replaced.')) {
                    try {
                      const { versions: vApi } = await import('@/lib/api');
                      await vApi.restorePost(siteId, postId, v.id);
                      window.location.reload();
                    } catch { /* ignore */ }
                  }
                }} className="text-[10px] text-primary hover:underline">Restore</button>
              </div>
            ))}
          </div>
        )}
        {showVersions && (!versions || versions.length === 0) && (
          <p className="text-[10px] text-base-content/25 mt-2">No revisions yet. Revisions are created automatically when you save.</p>
        )}
      </div>

      {/* Quick links */}
      <div className="border-t border-base-300/20 pt-3 space-y-1.5">
        {status === 'published' && slug && (
          <a href={`/blog/${slug}`} target="_blank" rel="noopener"
            className="btn btn-ghost btn-xs w-full text-[11px] gap-1 text-primary justify-start">
            <Eye size={11} /> View published post
          </a>
        )}
        <a href={`/site/blog/${slug}`} target="_blank" rel="noopener"
          className="btn btn-ghost btn-xs w-full text-[11px] gap-1 text-base-content/40 justify-start">
          <Eye size={11} /> Preview (dynamic)
        </a>
      </div>
    </div>
  );
}
