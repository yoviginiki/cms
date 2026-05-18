import { useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, LayoutList, Eye, Layers,
  PlusCircle,
} from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { useEditorShortcuts } from '@/hooks/useEditorShortcuts';
import { BuilderCanvas, BuilderDndProvider } from '@/components/editor/BuilderCanvas';
import { BlockSettings } from '@/components/editor/BlockSettings';
import { LayersPanel } from '@/components/editor/LayersPanel';
import { BlockPicker } from '@/components/editor/BlockPicker';
import { blocks as blocksApi, themeTemplates } from '@/lib/api';

import '@/components/blocks';

type RightTab = 'settings' | 'layers' | 'blocks' | 'template';

export default function TemplateEditor() {
  const { siteId = '', templateId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const setBlocks = useEditorStore((s) => s.setBlocks);
  const editorBlocks = useEditorStore((s) => s.blocks);
  const isDirty = useEditorStore((s) => s.isDirty);
  const isSaving = useEditorStore((s) => s.isSaving);
  const setSaving = useEditorStore((s) => s.setSaving);
  const setDirty = useEditorStore((s) => s.setDirty);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);

  const [rightTab, setRightTab] = useState<RightTab>('blocks');
  const [saveError, setSaveError] = useState('');
  const [name, setName] = useState('');
  const [nameDirty, setNameDirty] = useState(false);

  // Fetch template metadata
  const { data: template, isLoading: templateLoading } = useQuery<any>({
    queryKey: ['template', siteId, templateId],
    queryFn: () => themeTemplates.get(siteId, templateId).then((r: any) => r.data?.data),
  });

  // Fetch template blocks
  const { data: fetchedBlocks, isLoading: blocksLoading } = useQuery<any[]>({
    queryKey: ['template-blocks', siteId, templateId],
    queryFn: () => blocksApi.get(siteId, 'templates', templateId).then((r: any) => r.data?.data || []),
  });

  useEditorShortcuts(siteId, 'templates', templateId);

  // Load blocks once
  const blocksLoadedRef = useRef(false);
  useEffect(() => {
    if (fetchedBlocks && !blocksLoadedRef.current) {
      setBlocks(fetchedBlocks);
      blocksLoadedRef.current = true;
    }
  }, [fetchedBlocks, setBlocks]);

  // Load template name
  useEffect(() => {
    if (template && !nameDirty) {
      setName(template.name || '');
    }
  }, [template, nameDirty]);

  useEffect(() => {
    if (selectedBlockId) setRightTab('settings');
  }, [selectedBlockId]);

  // Warn on unsaved changes
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (isDirty || nameDirty) e.preventDefault(); };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty, nameDirty]);

  async function handleSave() {
    setSaving(true);
    setSaveError('');
    try {
      if (nameDirty) {
        await themeTemplates.update(siteId, templateId, { name });
        setNameDirty(false);
      }
      await blocksApi.sync(siteId, 'templates', templateId, editorBlocks);
      setDirty(false);
      queryClient.invalidateQueries({ queryKey: ['template', siteId, templateId] });
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message;
      setSaveError(msg);
    } finally {
      setSaving(false);
    }
  }

  const isLoading = templateLoading || blocksLoading;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  const TYPE_LABELS: Record<string, string> = {
    post: 'Single Post', archive: 'Category Archive', header: 'Global Header',
    footer: 'Global Footer', '404': '404 Page', search: 'Search Results',
  };

  const tabs: { key: RightTab; icon: typeof Eye; label: string }[] = [
    { key: 'template', icon: Eye, label: 'Template' },
    { key: 'settings', icon: LayoutList, label: 'Block' },
    { key: 'blocks', icon: PlusCircle, label: 'Add' },
    { key: 'layers', icon: Layers, label: 'Layers' },
  ];

  return (
    <BuilderDndProvider>
      <div className="flex flex-col h-screen bg-base-200">
        {/* Top bar */}
        <div className="h-12 bg-base-100 border-b border-base-300/50 flex items-center px-3 gap-3 shrink-0">
          <button onClick={() => navigate(`/sites/${siteId}/templates`)}
            className="btn btn-ghost btn-sm btn-square">
            <ArrowLeft size={16} />
          </button>
          <div className="flex items-center gap-2 flex-1 min-w-0">
            <span className="text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded font-medium">
              {TYPE_LABELS[template?.type] || 'Template'}
            </span>
            <input
              value={name}
              onChange={e => { setName(e.target.value); setNameDirty(true); }}
              className="text-sm font-semibold bg-transparent border-none outline-none flex-1 min-w-0"
              placeholder="Template name"
            />
          </div>
          {saveError && <span className="text-xs text-error">{saveError}</span>}
          <div className="flex items-center gap-1">
            {(isDirty || nameDirty) && (
              <span className="text-[10px] text-warning mr-1">Unsaved</span>
            )}
            <button onClick={handleSave} disabled={isSaving || (!isDirty && !nameDirty)}
              className="btn btn-primary btn-sm gap-1">
              {isSaving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
              Save
            </button>
          </div>
        </div>

        {/* Main area */}
        <div className="flex flex-1 overflow-hidden">
          {/* Canvas */}
          <div className="flex-1 overflow-auto">
            <BuilderCanvas />
          </div>

          {/* Right sidebar */}
          <div className="w-72 bg-base-100 border-l border-base-300/50 flex flex-col shrink-0">
            <div className="flex border-b border-base-300/30">
              {tabs.map(tab => (
                <button key={tab.key} onClick={() => setRightTab(tab.key)}
                  className={`flex-1 py-2 text-[10px] font-medium flex flex-col items-center gap-0.5 transition-colors ${
                    rightTab === tab.key ? 'text-primary border-b-2 border-primary' : 'text-base-content/40 hover:text-base-content/60'
                  }`}>
                  <tab.icon size={14} strokeWidth={1.5} />
                  {tab.label}
                </button>
              ))}
            </div>
            <div className="flex-1 overflow-y-auto">
              {rightTab === 'settings' && <BlockSettings />}
              {rightTab === 'blocks' && <div className="h-full"><BlockPicker /></div>}
              {rightTab === 'layers' && <LayersPanel />}
              {rightTab === 'template' && (
                <TemplateSettingsPanel template={template} />
              )}
            </div>
          </div>
        </div>
      </div>
    </BuilderDndProvider>
  );
}

function TemplateSettingsPanel({ template }: { template: any }) {
  if (!template) return null;

  return (
    <div className="p-3 space-y-4">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-3 rounded">
        <p className="font-medium mb-1">Template Info</p>
        <p>Use dynamic blocks (Post Title, Post Content, Post Image, etc.) to define how posts render with this template.</p>
      </div>

      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block">Type</label>
        <p className="text-sm font-medium">{template.type}</p>
      </div>

      {template.category && (
        <div>
          <label className="text-[11px] text-base-content/40 mb-1 block">Category</label>
          <p className="text-sm">{template.category.name}</p>
        </div>
      )}

      {template.post_format && template.post_format !== 'standard' && (
        <div>
          <label className="text-[11px] text-base-content/40 mb-1 block">Post Format</label>
          <p className="text-sm capitalize">{template.post_format}</p>
        </div>
      )}

      <div>
        <label className="text-[11px] text-base-content/40 mb-1 block">Default</label>
        <p className="text-sm">{template.is_default ? 'Yes — applies to all matching posts' : 'No — only when explicitly assigned'}</p>
      </div>

      <div className="border-t border-base-300/20 pt-3">
        <h4 className="text-[11px] text-base-content/40 mb-2 font-medium">Quick Start</h4>
        <p className="text-[11px] text-base-content/50 leading-relaxed">
          Add a <strong>Section</strong> block, then inside it place dynamic blocks like:
        </p>
        <ul className="text-[11px] text-base-content/50 mt-1 space-y-0.5 list-disc list-inside">
          <li><strong>Post Title</strong> — the post heading</li>
          <li><strong>Post Image</strong> — featured image</li>
          <li><strong>Post Meta</strong> — date, author, category</li>
          <li><strong>Post Content</strong> — the full post body</li>
          <li><strong>Post Navigation</strong> — prev/next links</li>
        </ul>
      </div>
    </div>
  );
}
