import { useEffect, useRef, useState } from 'react';
import { TranslationsPanel } from '@/components/editor/TranslationsPanel';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, LayoutList, Paintbrush, LayoutTemplate, Eye,
  Calendar, FolderTree, Clock, History, Globe, ChevronDown, Download, Upload,
} from 'lucide-react';
import { deepCloneWithNewIds } from '@/lib/builderHelpers';
import { useToast } from '@/components/ui/Toast';
import { usePostData } from '@/hooks/usePageData';
import { useAutoSave } from '@/hooks/useAutoSave';
import { useEditorShortcuts } from '@/hooks/useEditorShortcuts';
import { useThemeFonts } from '@/hooks/useThemeFonts';
import { useEditorStore } from '@/stores/editorStore';
import { useCanvasStore } from '@/stores/canvasStore';
import { CanvasEditor } from '@/components/canvas/CanvasEditor';
import { BuilderCanvas, BuilderDndProvider } from '@/components/editor/BuilderCanvas';
import { MagazineEditorCanvas } from '@/components/editor/MagazineEditorCanvas';
import { BlockSettings } from '@/components/editor/BlockSettings';
import { LayersPanel } from '@/components/editor/LayersPanel';
import { StructurePanel } from '@/components/editor/StructurePanel';
import { BlockPicker } from '@/components/editor/BlockPicker';
import { api, blocks as blocksApi, posts as postsApi, categories as categoriesApi, versions as versionsApi, publishing, sites, themeTemplates } from '@/lib/api';
import { AssetField } from '@/components/ui/AssetPicker';
import { SeoPanel } from '@/components/editor/SeoPanel';
import WysiwygEditor from '@/components/editor/WysiwygEditor';
import { slugify } from '@/lib/slugify';

import '@/components/blocks';

type EditorMode = 'simple' | 'block' | 'magazine' | 'canvas';

// Human-readable page-builder names, shared by the toggle labels and the
// "switch page builder" confirmation dialog.
const MODE_LABELS: Record<EditorMode, string> = {
  simple: 'Simple',
  block: 'Blocks',
  canvas: 'Canvas',
  magazine: 'Magazine',
};
type RightTab = 'settings' | 'post' | 'layers' | 'blocks' | 'tree';

// A Simple-mode post keeps its body in ONE rich-text/text block that lives in
// the SAME block tree autosave/save/publish persist. Find it anywhere in the
// tree (it may be nested inside a section/row/column skeleton).
function findContentBlock(blocks: any[]): any | null {
  for (const b of blocks || []) {
    if (b?.type === 'rich-text' || b?.type === 'text') return b;
    const nested = b?.children ? findContentBlock(b.children) : null;
    if (nested) return nested;
  }
  return null;
}

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
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const { toast } = useToast();
  // Hidden <input type=file> that the Import button clicks.
  const importInputRef = useRef<HTMLInputElement | null>(null);

  const [editorMode, setEditorMode] = useState<EditorMode>('simple');
  const [simpleContent, setSimpleContent] = useState('');
  // Id of the block that holds Simple-mode body content (kept in sync with the store).
  const simpleBlockIdRef = useRef<string | null>(null);
  // Set while we deliberately reload the page after switching page builder, so
  // the beforeunload guard below doesn't throw a second "unsaved changes" prompt.
  const bypassUnloadRef = useRef(false);
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
  // Which post template renders this post: 'default' (site default template),
  // 'none' (Empty — render the builder's own output), or a template UUID.
  const [templateId, setTemplateId] = useState<string>('default');
  const [metaDirty, setMetaDirty] = useState(false);
  const [slugManual, setSlugManual] = useState(false);
  const [authorId, setAuthorId] = useState('');
  // SEO edits accumulate as a partial patch; the backend merges it into
  // seo_meta so canvas config / custom scripts are never clobbered.
  const [seoPatch, setSeoPatch] = useState<Record<string, unknown>>({});

  // Site domain for View links
  const { data: siteData } = useQuery<any>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then((r: any) => r.data.data),
  });
  const publicBase = siteData?.custom_domain ? `https://${siteData.custom_domain}` : `https://ensodo.eu/${(siteData?.settings?.deploy_slug as string) || siteData?.slug || ''}`;

  // Layouts
  const { data: layoutsList } = useQuery<any[]>({
    queryKey: ['layouts', siteId],
    queryFn: () => api.get(`/sites/${siteId}/layouts`).then((r: any) => {
      const d = r.data?.data;
      return Array.isArray(d) ? d : [];
    }),
  });

  // Post templates (from the site's Templates page) — used by the Template picker.
  const { data: postTemplates } = useQuery<any[]>({
    queryKey: ['post-templates', siteId],
    queryFn: () => themeTemplates.list(siteId).then((r: any) => {
      const d = r.data?.data ?? r.data;
      return Array.isArray(d) ? d.filter((t: any) => t.type === 'post') : [];
    }),
  });

  // Categories & tags
  const { data: categoriesList } = useQuery({
    queryKey: ['categories', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data),
  });

  // Authors — /users is admin-gated; the picker hides itself when the list 403s
  const { data: usersList } = useQuery<any[]>({
    queryKey: ['users'],
    queryFn: () => api.get('/users').then((r: any) => r.data.data),
    retry: false,
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
      // Extract simple content from the body text/rich-text block — anywhere in
      // the tree — and remember its id so Simple-mode edits update THAT block.
      const textBlock = findContentBlock(fetchedBlocks);
      simpleBlockIdRef.current = textBlock?.id ?? null;
      if (textBlock?.data?.content) {
        setSimpleContent(textBlock.data.content as string);
      }
      // Canvas mode reads the SAME block tree into the canvas store.
      if (post?.editor_mode === 'canvas') {
        const cv = (post?.seo_meta as { canvas?: { page_type?: string; width?: number; mobile_width?: number } } | undefined)?.canvas;
        useCanvasStore.getState().loadFromBlocks(fetchedBlocks, {
          pageType: cv?.page_type === 'single' ? 'single' : 'website',
          width: cv?.width,
          mobileWidth: cv?.mobile_width,
        });
      }
    }
  }, [fetchedBlocks, setBlocks, post]);

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
    setAuthorId(post.author_id || '');
    setPublishedAt(post.published_at ? new Date(post.published_at).toISOString().slice(0, 16) : '');
    setScheduledAt(post.scheduled_at ? new Date(post.scheduled_at).toISOString().slice(0, 16) : '');
    setSlugManual(!!post.slug);
    if (['simple', 'block', 'magazine', 'canvas'].includes(post.editor_mode)) {
      setEditorMode(post.editor_mode as EditorMode);
      setStoreEditorMode(post.editor_mode as EditorMode);
    }
    // Template choice: explicit if stored, otherwise the builder's default
    // (Simple → default template, other builders → Empty) — matching how the
    // publisher resolves an unset choice.
    const storedTpl = (post.seo_meta as { template_id?: string } | undefined)?.template_id;
    setTemplateId(storedTpl ?? (post.editor_mode === 'simple' ? 'default' : 'none'));
  }, [post]);

  useEffect(() => {
    // Selecting a block jumps to its settings — unless navigating the Tree.
    if (selectedBlockId) setRightTab(t => (t === 'tree' ? t : 'settings'));
  }, [selectedBlockId]);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (!bypassUnloadRef.current && (isDirty || metaDirty)) e.preventDefault(); };
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
        editor_mode: editorMode, author_id: authorId || null,
        published_at: publishedAt || null, scheduled_at: scheduledAt || null,
        seo_meta: { ...seoPatch, template_id: templateId },
      });
      setMetaDirty(false);
      // Save blocks — in simple mode, wrap content in a single text block
      if (editorMode === 'canvas') {
        await blocksApi.sync(siteId, 'posts', postId, useCanvasStore.getState().toBlocks());
        useCanvasStore.getState().markClean();
      } else {
        // Simple & block modes share ONE source of truth: the block store.
        // (Simple-mode edits are written into the store via handleSimpleChange,
        // so nothing lives only in `simpleContent` waiting to be lost.)
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
        editor_mode: editorMode, author_id: authorId || null,
        published_at: pubDate, scheduled_at: scheduledAt || null,
        seo_meta: { ...seoPatch, template_id: templateId },
      });
      // Save blocks
      if (editorMode === 'canvas') {
        await blocksApi.sync(siteId, 'posts', postId, useCanvasStore.getState().toBlocks());
        useCanvasStore.getState().markClean();
      } else {
        // Simple & block modes share ONE source of truth: the block store.
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

  // Simple editor writes straight into the block store — the SINGLE source of
  // truth that autosave/save/publish all persist. Previously the HTML lived
  // only in `simpleContent` React state, so autosave shipped the empty block
  // skeleton, cleared the dirty flag, and the typed content was silently lost.
  function handleSimpleChange(html: string) {
    setSimpleContent(html);
    const blocks = useEditorStore.getState().blocks;
    const existing = findContentBlock(blocks);
    if (existing) {
      simpleBlockIdRef.current = existing.id;
      updateBlock(existing.id, { content: html }); // merges data + marks dirty
    } else {
      // Empty post: create the body block, preserving any layout skeleton.
      const newId = (globalThis.crypto as { randomUUID?: () => string })?.randomUUID?.() ?? `blk-${Date.now()}`;
      simpleBlockIdRef.current = newId;
      setBlocks([
        { id: newId, type: 'rich-text', level: 'module', data: { content: html }, style: {}, order: 0, children: [] },
        ...blocks,
      ] as any);
      setDirty(true);
    }
  }

  // Switching page builder is destructive: each builder stores/reads the block
  // tree differently, so we warn, persist the current work under the newly
  // chosen builder, then hard-reload so the correct editor mounts cleanly.
  async function switchEditorMode(mode: EditorMode) {
    if (mode === editorMode || isSaving) return;
    const confirmed = window.confirm(
      `Смяна на page builder към „${MODE_LABELS[mode]}“?\n\n` +
      `Различните page builder-и съхраняват съдържанието по различен начин. ` +
      `Възможно е част от текущото съдържание да се загуби или да изглежда различно.\n\n` +
      `Страницата ще се презареди с избрания page builder.`
    );
    if (!confirmed) return;
    setSaving(true);
    setSaveError('');
    try {
      // Persist the chosen builder together with whatever is currently in the
      // editor, so the reload re-opens the correct editor over the saved content.
      // Reset the template to the new builder's default (Simple → default
      // template, others → Empty) so a template can't override a custom layout.
      await postsApi.update(siteId, postId, {
        editor_mode: mode,
        seo_meta: { template_id: mode === 'simple' ? 'default' : 'none' },
      });
      if (editorMode === 'canvas') {
        await blocksApi.sync(siteId, 'posts', postId, useCanvasStore.getState().toBlocks());
      } else {
        await blocksApi.sync(siteId, 'posts', postId, useEditorStore.getState().blocks);
      }
      // Reload into the new builder without the browser's unsaved-changes prompt.
      bypassUnloadRef.current = true;
      window.location.reload();
    } catch (err: any) {
      const msg = err.response?.data?.message || (err.response?.data?.errors ? JSON.stringify(err.response.data.errors) : err.message);
      setSaveError(msg);
      setSaving(false);
    }
  }

  // ─── Export / Import content ───────────────────────────────────────────
  // A post's content is a block tree. Export writes it to a JSON file with the
  // block ids STRIPPED — ids identify a block within one post (stable anchors,
  // theme overrides, grid positions), so a copy moved to another post must get
  // fresh ids, never inherit the source's. Import regenerates ids on the way in.

  function stripBlockIds(b: any): any {
    // Drop the DB id; keep everything else (type/data/style/children).
    const { id: _drop, ...rest } = b || {};
    return { ...rest, children: (b?.children || []).map(stripBlockIds) };
  }

  function handleExportContent() {
    const source = editorMode === 'canvas'
      ? useCanvasStore.getState().toBlocks()
      : useEditorStore.getState().blocks;
    const blocks = (source || []).map(stripBlockIds);
    if (blocks.length === 0) {
      toast({ type: 'info', message: 'Няма съдържание за експорт.' });
      return;
    }
    const payload = { __ensodo_content: 1, editor_mode: editorMode, exported_title: title, blocks };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${slug || 'post'}-content.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    toast({ type: 'success', message: 'Съдържанието е експортирано.' });
  }

  async function handleImportFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    e.target.value = ''; // allow re-importing the same file
    if (!file) return;
    try {
      const text = await file.text();
      const parsed = JSON.parse(text);
      const raw = Array.isArray(parsed) ? parsed : parsed?.blocks;
      if (!Array.isArray(raw) || raw.length === 0) {
        toast({ type: 'error', message: 'Невалиден файл: няма блокове.' });
        return;
      }
      const existing = useEditorStore.getState().blocks;
      if (existing.length > 0 && !window.confirm(
        'Импортът ще замени текущото съдържание на тази публикация. Продължавате?'
      )) return;

      // Fresh ids so imported content never collides with the source post.
      const imported = raw.map((b: any) => deepCloneWithNewIds(b));
      setBlocks(imported as any);

      // Re-derive per-mode editor state from the new tree.
      if (editorMode === 'simple') {
        const body = findContentBlock(imported);
        simpleBlockIdRef.current = body?.id ?? null;
        setSimpleContent((body?.data?.content as string) || '');
      } else if (editorMode === 'canvas') {
        const cv = (post?.seo_meta as { canvas?: { page_type?: string; width?: number; mobile_width?: number } } | undefined)?.canvas;
        useCanvasStore.getState().loadFromBlocks(imported as any, {
          pageType: cv?.page_type === 'single' ? 'single' : 'website',
          width: cv?.width,
          mobileWidth: cv?.mobile_width,
        });
      }
      setDirty(true);
      toast({ type: 'success', message: `Импортирани ${imported.length} блока. Натиснете Save.` });
    } catch {
      toast({ type: 'error', message: 'Импортът се провали: файлът не е валиден JSON.' });
    }
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
      <div className="flex items-center justify-between gap-3 h-12 px-4 bg-base-100 border-b border-base-300/30 shrink-0 overflow-x-auto">
        <div className="flex items-center gap-3 min-w-0">
          <button onClick={() => navigate(`/sites/${siteId}/posts`)} className="btn btn-ghost btn-xs btn-square shrink-0"><ArrowLeft size={16} /></button>
          <input value={title} onChange={e => { setTitle(e.target.value); if (!slugManual) setSlug(slugify(e.target.value)); markMetaDirty(); }}
            className="text-sm font-medium bg-transparent border-none outline-none text-base-content/90 w-48 min-w-0" placeholder="Post title" />
          <span className={`badge badge-sm ${status === 'published' ? 'badge-success' : 'badge-ghost'} badge-outline text-[10px]`}>{status}</span>
          {(isDirty || metaDirty) && <span className="text-[10px] text-warning font-medium">unsaved</span>}
          {saveError && <span className="text-[10px] text-error truncate max-w-xs">{saveError}</span>}
        </div>

        <div className="flex items-center gap-2 shrink-0">
          {/* Page builder toggle */}
          <span className="text-[11px] font-medium text-base-content/50 select-none">Pagebuilder:</span>
          <div className="flex bg-base-200/80 rounded-md p-0.5">
            <button onClick={() => switchEditorMode('simple')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'simple' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              Simple
            </button>
            <button onClick={() => switchEditorMode('block')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'block' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              <LayoutList size={12} /> Blocks
            </button>
            <button onClick={() => switchEditorMode('canvas')}
              title="Freeform section canvases — drag & position blocks"
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'canvas' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              <LayoutTemplate size={12} /> Canvas
            </button>
            <button onClick={() => switchEditorMode('magazine')}
              title="Page-based magazine layout (legacy)"
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${editorMode === 'magazine' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40'}`}>
              <Paintbrush size={12} /> Magazine
            </button>
          </div>

          {/* Template — which post template wraps the content ('Empty' = the
              builder's own output, no template chrome overriding it). */}
          <span className="text-[11px] font-medium text-base-content/50 select-none">Template:</span>
          <select
            value={templateId}
            onChange={e => { setTemplateId(e.target.value); markMetaDirty(); }}
            title="Which template renders this post. 'Empty' keeps your builder's own layout."
            className="select select-bordered select-xs text-[11px] max-w-[9rem]">
            <option value="default">Default</option>
            {(postTemplates || []).map((t: any) => (
              <option key={t.id} value={t.id}>{t.name}{t.is_default ? ' (default)' : ''}</option>
            ))}
            <option value="none">Empty (no template)</option>
          </select>

          <div className="w-px h-5 bg-base-300/30" />
          {/* Export / Import content (block tree, ids stripped) — copy content
              from one post into another without carrying source block ids. */}
          <button onClick={handleExportContent}
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="Export this post's content to a JSON file">
            <Download size={13} /> Export
          </button>
          <button onClick={() => importInputRef.current?.click()}
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="Import content from an exported JSON file (replaces current content)">
            <Upload size={13} /> Import
          </button>
          <input ref={importInputRef} type="file" accept="application/json,.json"
            onChange={handleImportFile} className="hidden" />
          <div className="w-px h-5 bg-base-300/30" />
          {/* Preview = dynamic render from the DB (reflects unpublished edits).
              View = the live published static page. */}
          <a href={`/site/blog/${slug}`}
            target="_blank" rel="noopener"
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="Preview (dynamic — shows unpublished edits)">
            <Eye size={13} /> Preview
          </a>
          <a href={(() => {
              const cat = categoriesList?.find((c: any) => c.id === categoryId);
              return `${publicBase}/${cat?.slug ? cat.slug + '/' : ''}${slug}`;
            })()}
            target="_blank" rel="noopener"
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="View the live published page">
            <Globe size={13} /> Live
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
        {editorMode === 'canvas' ? (
          <CanvasEditor siteId={siteId} pageId={postId} contentType="posts" seoMeta={post?.seo_meta} onDirty={() => setDirty(true)} />
        ) : editorMode === 'simple' ? (
          /* Simple WYSIWYG editor — full screen, classic WordPress-like */
          <div className="flex flex-1 overflow-x-auto overflow-y-hidden lg:overflow-x-hidden snap-x snap-mandatory">
            <div className="w-full min-w-full lg:min-w-0 lg:flex-1 snap-start overflow-y-auto p-4 lg:p-8">
              <div className="max-w-3xl mx-auto">
                <WysiwygEditor
                  content={simpleContent}
                  onChange={handleSimpleChange}
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
                  seoMeta={{ ...(post?.seo_meta || {}), ...seoPatch }} site={siteData}
                  onSeoPatch={patch => { setSeoPatch(p => ({ ...p, ...patch })); setMetaDirty(true); }}
                  authorId={authorId} setAuthorId={id => { setAuthorId(id); setMetaDirty(true); }}
                  authors={usersList || []}
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
                          seoMeta={{ ...(post?.seo_meta || {}), ...seoPatch }} site={siteData}
                  onSeoPatch={patch => { setSeoPatch(p => ({ ...p, ...patch })); setMetaDirty(true); }}
                  authorId={authorId} setAuthorId={id => { setAuthorId(id); setMetaDirty(true); }}
                  authors={usersList || []}
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
                  { key: 'tree' as RightTab, label: 'Tree' },
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
                {rightTab === 'tree' && <StructurePanel />}
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
                    seoMeta={{ ...(post?.seo_meta || {}), ...seoPatch }} site={siteData}
                  onSeoPatch={patch => { setSeoPatch(p => ({ ...p, ...patch })); setMetaDirty(true); }}
                  authorId={authorId} setAuthorId={id => { setAuthorId(id); setMetaDirty(true); }}
                  authors={usersList || []}
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
                    seoMeta={{ ...(post?.seo_meta || {}), ...seoPatch }} site={siteData}
                  onSeoPatch={patch => { setSeoPatch(p => ({ ...p, ...patch })); setMetaDirty(true); }}
                  authorId={authorId} setAuthorId={id => { setAuthorId(id); setMetaDirty(true); }}
                  authors={usersList || []}
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
function PostMetaPanel({ slug, setSlug, slugManual, setSlugManual, title, status, setStatus, categoryId, setCategoryId, layoutId, setLayoutId, layouts, excerpt, setExcerpt, featuredImage, setFeaturedImage, videoUrl, setVideoUrl, thumbnail, setThumbnail, postFormat, setPostFormat, publishedAt, setPublishedAt, scheduledAt, setScheduledAt, categories, versions, siteId, postId, seoMeta, site, onSeoPatch, authorId, setAuthorId, authors }: {
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
  seoMeta?: Record<string, unknown> | null; site?: any;
  onSeoPatch?: (patch: Record<string, unknown>) => void;
  authorId?: string; setAuthorId?: (id: string) => void;
  authors?: Array<{ id: string; name: string }>;
}) {
  const [showVersions, setShowVersions] = useState(false);
  const publicBase = site?.custom_domain ? `https://${site.custom_domain}` : `https://ensodo.eu/${(site?.settings?.deploy_slug as string) || site?.slug || ''}`;
  const categorySlug = (categories as any[]).find((c: any) => c.id === categoryId)?.slug;
  const postPath = categorySlug ? `/${categorySlug}/${slug}` : `/${slug}`;

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

      {/* Author */}
      {!!authors?.length && setAuthorId && (
        <div>
          <label className="text-[11px] text-base-content/40 mb-1 block">Author</label>
          <select value={authorId || ''} onChange={e => setAuthorId(e.target.value)}
            className="select select-bordered select-sm w-full text-[12px]">
            <option value="">No author</option>
            {authors.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
          <p className="text-[10px] text-base-content/25 mt-0.5">Shown in article structured data</p>
        </div>
      )}

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

      {/* SEO */}
      {onSeoPatch && (
        <div className="border-t border-base-300/20 pt-3">
          <label className="text-[11px] text-base-content/40 mb-1 block font-medium">SEO</label>
          <SeoPanel
            values={(seoMeta || {}) as any}
            onPatch={onSeoPatch}
            fallbackTitle={title}
            fallbackDescription={excerpt}
            titleTemplate={site?.seo_defaults?.title_template}
            siteName={site?.name}
            urlBase={publicBase}
            path={postPath}
          />
        </div>
      )}

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

      {/* Translations */}
      <div className="border-t border-base-300/20 pt-3">
        <label className="text-[11px] text-base-content/40 mb-1 block">Translations</label>
        <TranslationsPanel siteId={siteId} contentType="posts" contentId={postId}
          seoMeta={seoMeta} site={site} />
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
