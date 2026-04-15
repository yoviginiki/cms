import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Save, Loader2 } from 'lucide-react';
import { usePostData } from '@/hooks/usePageData';
import { useAutoSave } from '@/hooks/useAutoSave';
import { useEditorShortcuts } from '@/hooks/useEditorShortcuts';
import { useEditorStore } from '@/stores/editorStore';
import { BuilderCanvas } from '@/components/editor/BuilderCanvas';
import { BuilderSidebar } from '@/components/editor/BuilderSidebar';
import { blocks as blocksApi } from '@/lib/api';

import '@/components/blocks';

export default function PostEditor() {
  const { siteId = '', postId = '' } = useParams();
  const navigate = useNavigate();
  const { post, blocks: fetchedBlocks, isLoading, error } = usePostData(siteId, postId);
  const setBlocks = useEditorStore((s) => s.setBlocks);
  const editorBlocks = useEditorStore((s) => s.blocks);
  const isDirty = useEditorStore((s) => s.isDirty);
  const isSaving = useEditorStore((s) => s.isSaving);
  const setSaving = useEditorStore((s) => s.setSaving);
  const setDirty = useEditorStore((s) => s.setDirty);

  useAutoSave(siteId, 'posts', postId);
  useEditorShortcuts(siteId, 'posts', postId);

  useEffect(() => {
    if (fetchedBlocks) setBlocks(fetchedBlocks);
  }, [fetchedBlocks, setBlocks]);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (isDirty) e.preventDefault(); };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty]);

  async function handleSave() {
    setSaving(true);
    try {
      await blocksApi.sync(siteId, 'posts', postId, editorBlocks);
      setDirty(false);
    } catch { /* noop */ } finally { setSaving(false); }
  }

  if (isLoading) {
    return <div className="flex items-center justify-center h-screen"><Loader2 className="w-8 h-8 animate-spin text-blue-500" /></div>;
  }

  if (error) {
    return <div className="flex items-center justify-center h-screen text-red-500">Failed to load post</div>;
  }

  return (
    <div className="flex flex-col h-screen bg-gray-100">
      <div className="flex items-center justify-between px-4 py-2 bg-white border-b border-gray-200 shrink-0">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/posts`)} className="p-1.5 hover:bg-gray-100 rounded-md">
            <ArrowLeft size={18} />
          </button>
          <div>
            <h1 className="text-lg font-semibold">{post?.title ?? 'Post'}</h1>
            <span className="text-xs text-gray-500">/{post?.slug}</span>
          </div>
          <span className={`text-xs px-2 py-0.5 rounded-full ${post?.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>
            {post?.status ?? 'draft'}
          </span>
          {isDirty && <span className="text-xs text-orange-500 font-medium">Unsaved changes</span>}
        </div>
        <div className="flex items-center gap-2">
          <button onClick={handleSave} disabled={isSaving || !isDirty}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {isSaving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
            {isSaving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </div>
      <div className="flex flex-1 overflow-hidden">
        <BuilderCanvas />
        <BuilderSidebar />
      </div>
    </div>
  );
}
