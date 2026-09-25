import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, LayoutList, Info, Layers, PlusCircle, ListTree, Rocket, Undo2, LayoutGrid,
} from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { useEditorShortcuts } from '@/hooks/useEditorShortcuts';
import { saveContent, reloadSessionFromServer, sessionKeyFor, SaveConflictError } from '@/lib/saveCoordinator';
import { hydrateEditorSession } from '@/lib/editorHydration';
import { BuilderCanvas, BuilderDndProvider } from '@/components/editor/BuilderCanvas';
import { BlockSettings } from '@/components/editor/BlockSettings';
import { LayersPanel } from '@/components/editor/LayersPanel';
import { StructurePanel } from '@/components/editor/StructurePanel';
import { BlockPicker } from '@/components/editor/BlockPicker';
import { blocks as blocksApi, globalSections } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

import '@/components/blocks';

type RightTab = 'section' | 'settings' | 'layers' | 'blocks' | 'tree';

interface GridArea { grid_id: string; grid_name: string; area: string; label: string }
interface SectionDetail {
  section: { id: string; name: string; status: 'draft' | 'published'; published_at: string | null };
  usage: { count: number };
  grid_areas: GridArea[];
}

/**
 * Block editor for a Global Section — the content of grid areas (site header,
 * footer, sidebar) and of global_ref embeds. Same editor as pages/templates.
 */
export default function SectionEditor() {
  const { siteId = '', sectionId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const isDirty = useEditorStore((s) => s.isDirty);
  const isSaving = useEditorStore((s) => s.isSaving);
  const setSaving = useEditorStore((s) => s.setSaving);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);

  const [rightTab, setRightTab] = useState<RightTab>('blocks');
  const [saveError, setSaveError] = useState('');
  const [name, setName] = useState('');
  const [nameDirty, setNameDirty] = useState(false);
  const [publishing, setPublishing] = useState(false);

  const { data: detail, isLoading: detailLoading } = useQuery<SectionDetail>({
    queryKey: ['global-section', siteId, sectionId],
    queryFn: () => globalSections.get(siteId, sectionId).then((r: any) => r.data?.data),
  });

  const { data: fetched, isLoading: blocksLoading } = useQuery<{ data: any[]; version: string | null }>({
    queryKey: ['global-section-blocks', siteId, sectionId],
    queryFn: () => blocksApi.get(siteId, 'global-sections', sectionId).then((r: any) => ({ data: r.data?.data || [], version: r.data?.version ?? null })),
  });
  const fetchedBlocks = fetched?.data;

  useEditorShortcuts(siteId, 'global-sections', sectionId, () => handleSave());

  const sessionKey = sessionKeyFor({ siteId, type: 'global-sections', id: sectionId });
  const hydrated = useEditorStore((s) => s.hydrated);
  const conflict = useEditorStore((s) => s.conflict);
  useEffect(() => { useEditorStore.getState().beginSession(sessionKey); }, [sessionKey]);
  useEffect(() => {
    // Sections are always edited in block mode (never inherit a canvas page's mode)
    hydrateEditorSession({
      sessionKey, contentId: sectionId,
      meta: detail ? { ...detail.section, editor_mode: 'block' } : undefined,
      blocks: fetchedBlocks, blocksVersion: fetched?.version,
    });
  }, [sessionKey, sectionId, detail, fetchedBlocks, fetched?.version]);

  useEffect(() => {
    if (detail && !nameDirty) setName(detail.section.name || '');
  }, [detail, nameDirty]);

  useEffect(() => {
    if (selectedBlockId) setRightTab(t => (t === 'tree' ? t : 'settings'));
  }, [selectedBlockId]);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (isDirty || nameDirty) e.preventDefault(); };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty, nameDirty]);

  async function handleSave() {
    if (!hydrated) return;
    setSaving(true);
    setSaveError('');
    try {
      if (nameDirty) {
        await globalSections.update(siteId, sectionId, { name });
        setNameDirty(false);
      }
      const r = await saveContent({ siteId, type: 'global-sections', id: sectionId });
      if (r.outcome === 'skipped') throw new Error('Editor not ready (content still loading)');
      queryClient.invalidateQueries({ queryKey: ['global-section', siteId, sectionId] });
      if (detail?.section.status === 'published') {
        toast({ type: 'info', message: 'Saved — the change goes live with the next site rebuild (automatic when auto-publish is on).' });
      }
    } catch (err: any) {
      const msg = err instanceof SaveConflictError ? 'Conflict: this section was changed in another editor — reload' : (err.response?.data?.message || err.message);
      setSaveError(msg);
    } finally {
      setSaving(false);
    }
  }

  async function togglePublish() {
    if (!detail) return;
    if (isDirty || nameDirty) await handleSave();
    setPublishing(true);
    try {
      if (detail.section.status === 'published') {
        await globalSections.unpublish(siteId, sectionId);
        toast({ type: 'info', message: 'Unpublished — areas using it render empty after the rebuild.' });
      } else {
        await globalSections.publish(siteId, sectionId);
        toast({ type: 'success', message: 'Published — every page using it is rebuilt (automatic when auto-publish is on).' });
      }
      queryClient.invalidateQueries({ queryKey: ['global-section', siteId, sectionId] });
      queryClient.invalidateQueries({ queryKey: ['global-sections', siteId] });
    } catch (e: any) {
      toast({ type: 'error', message: e?.response?.data?.message || 'Failed' });
    } finally {
      setPublishing(false);
    }
  }

  if (detailLoading || blocksLoading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  const published = detail?.section.status === 'published';
  const areas = detail?.grid_areas ?? [];

  const tabs: { key: RightTab; icon: typeof Info; label: string }[] = [
    { key: 'section', icon: Info, label: 'Section' },
    { key: 'settings', icon: LayoutList, label: 'Block' },
    { key: 'blocks', icon: PlusCircle, label: 'Add' },
    { key: 'tree', icon: ListTree, label: 'Tree' },
    { key: 'layers', icon: Layers, label: 'Layers' },
  ];

  return (
    <BuilderDndProvider>
      <div className="flex flex-col h-screen bg-base-200">
        <div className="h-12 bg-base-100 border-b border-base-300/50 flex items-center px-3 gap-3 shrink-0">
          <button onClick={() => navigate(-1)} className="btn btn-ghost btn-sm btn-square" title="Back">
            <ArrowLeft size={16} />
          </button>
          <div className="flex items-center gap-2 flex-1 min-w-0">
            <span className="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">Global section</span>
            <input
              value={name}
              onChange={e => { setName(e.target.value); setNameDirty(true); }}
              className="text-sm font-semibold bg-transparent border-none outline-none flex-1 min-w-0"
              placeholder="Section name"
            />
            <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${published ? 'bg-green-100 text-green-700' : 'bg-base-200 text-base-content/50'}`}>
              {published ? 'Published' : 'Draft'}
            </span>
          </div>
          {saveError && <span className="text-xs text-error">{saveError}</span>}
          <div className="flex items-center gap-1">
            {(isDirty || nameDirty) && <span className="text-[10px] text-warning mr-1">Unsaved</span>}
            {conflict && (
              <button onClick={() => reloadSessionFromServer({ siteId, type: 'global-sections', id: sectionId }).then(() => setSaveError(''))} className="btn btn-xs btn-error text-[10px]">
                Conflict — reload
              </button>
            )}
            <button onClick={handleSave} disabled={isSaving || !hydrated || (!isDirty && !nameDirty)} className="btn btn-ghost btn-sm gap-1 border border-base-300">
              {isSaving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
              Save
            </button>
            <button onClick={togglePublish} disabled={publishing || !hydrated} className={`btn btn-sm gap-1 ${published ? 'btn-ghost border border-base-300' : 'btn-primary'}`}>
              {publishing ? <Loader2 size={14} className="animate-spin" /> : published ? <Undo2 size={14} /> : <Rocket size={14} />}
              {published ? 'Unpublish' : 'Publish'}
            </button>
          </div>
        </div>

        {areas.length > 0 && (
          <div className="bg-amber-50 text-amber-800 text-xs px-4 py-1.5 border-b border-amber-200 flex items-center gap-2">
            <LayoutGrid size={12} />
            Shared {areas.map(a => a.label || a.area).filter((v, i, arr) => arr.indexOf(v) === i).join(' / ').toLowerCase()} — changes apply to every page using {areas.length === 1 ? 'the grid' : `${areas.length} grid areas`}.
          </div>
        )}

        <div className="flex flex-1 overflow-x-auto overflow-y-hidden lg:overflow-x-hidden snap-x snap-mandatory">
          <div className="w-full min-w-full lg:min-w-0 lg:flex-1 snap-start flex flex-col overflow-hidden">
            <BuilderCanvas />
          </div>

          <div className="w-72 min-w-[288px] bg-base-100 border-l border-base-300/50 flex flex-col shrink-0 snap-start">
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
              {rightTab === 'tree' && <StructurePanel />}
              {rightTab === 'layers' && <LayersPanel />}
              {rightTab === 'section' && (
                <div className="p-3 space-y-4 text-sm">
                  <div className="bg-amber-50 text-amber-800 text-xs p-3 rounded leading-relaxed">
                    A global section is edited once and shown everywhere it is used: as a grid area (site header, footer, sidebar) or embedded in pages with the Global Section block.
                    {!published && <strong className="block mt-1">Draft — nothing shows until you publish it.</strong>}
                  </div>
                  <div>
                    <h4 className="text-[11px] text-base-content/40 mb-1 font-medium">Used as grid area</h4>
                    {areas.length === 0
                      ? <p className="text-xs text-base-content/40">Not used by any grid yet. In Grids, pick an area → type “Section” → this section.</p>
                      : <ul className="space-y-1">{areas.map((a, i) => (
                          <li key={i}>
                            <Link to={`/sites/${siteId}/grids/${a.grid_id}/edit`} className="text-xs text-primary hover:underline">
                              {a.grid_name} → {a.label || a.area}
                            </Link>
                          </li>
                        ))}</ul>}
                  </div>
                  <div>
                    <h4 className="text-[11px] text-base-content/40 mb-1 font-medium">References</h4>
                    <p className="text-xs text-base-content/60">{detail?.usage?.count ?? 0} (pages, posts and site-wide areas)</p>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </BuilderDndProvider>
  );
}
