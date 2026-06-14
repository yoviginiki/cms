/**
 * P4 — Visual Preset Browser with preview thumbnails.
 * Shows available section presets in a modal/dropdown with visual previews.
 * Includes Saved tab for Section Library (block templates).
 */
import { useState, useEffect } from 'react';
import { X, Search, PanelTop, Sparkles, BookMarked, Trash2 } from 'lucide-react';
import { presets as presetsList, type PresetDefinition } from '@/presets';
import { BlockIcon } from './BlockIcon';
import { api } from '@/lib/api';
import { useEditorStore } from '@/stores/editorStore';

interface BlockTemplate {
  id: string;
  name: string;
  category: string;
  description: string | null;
  blocks_data: any[];
  is_system: boolean;
}

interface PresetBrowserProps {
  open: boolean;
  onClose: () => void;
  onSelectPreset: (type: string) => void;
  onAddBlank: () => void;
  siteId?: string;
}

// Color themes for visual thumbnails
const PRESET_THEMES: Record<string, { bg: string; accent: string; text: string }> = {
  preset_hero: { bg: '#1e293b', accent: '#3b82f6', text: '#f8fafc' },
  preset_cta: { bg: '#7c3aed', accent: '#a78bfa', text: '#ffffff' },
  preset_features: { bg: '#f8fafc', accent: '#10b981', text: '#1e293b' },
  preset_testimonials: { bg: '#fefce8', accent: '#f59e0b', text: '#78350f' },
  preset_pricing: { bg: '#f0fdf4', accent: '#22c55e', text: '#14532d' },
  preset_faq: { bg: '#faf5ff', accent: '#a855f7', text: '#581c87' },
  preset_contact: { bg: '#f8fafc', accent: '#0ea5e9', text: '#0c4a6e' },
  preset_team: { bg: '#fff7ed', accent: '#f97316', text: '#7c2d12' },
  preset_stats: { bg: '#f8fafc', accent: '#3b82f6', text: '#1e3a5f' },
  preset_portfolio: { bg: '#fafafa', accent: '#ec4899', text: '#831843' },
  preset_blog_grid: { bg: '#f0fdf4', accent: '#059669', text: '#064e3b' },
};

function PresetThumbnail({ preset }: { preset: PresetDefinition }) {
  const theme = PRESET_THEMES[preset.type] || { bg: '#f1f5f9', accent: '#6366f1', text: '#334155' };

  return (
    <div style={{ background: theme.bg, borderRadius: '0.5rem', padding: '1rem', height: '120px', display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center', gap: '0.5rem' }}>
      {preset.type === 'preset_hero' && (
        <>
          <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', width: '100%' }}>
            <div style={{ flex: 1 }}>
              <div style={{ height: 8, background: theme.text, borderRadius: 2, width: '80%', marginBottom: 6, opacity: 0.9 }} />
              <div style={{ height: 5, background: theme.text, borderRadius: 2, width: '60%', marginBottom: 8, opacity: 0.4 }} />
              <div style={{ height: 14, background: theme.accent, borderRadius: 4, width: '40%' }} />
            </div>
            <div style={{ width: 50, height: 50, borderRadius: 6, background: theme.accent, opacity: 0.3 }} />
          </div>
        </>
      )}
      {preset.type === 'preset_cta' && (
        <div style={{ textAlign: 'center' }}>
          <div style={{ height: 8, background: theme.text, borderRadius: 2, width: 80, margin: '0 auto 6px', opacity: 0.9 }} />
          <div style={{ height: 5, background: theme.text, borderRadius: 2, width: 60, margin: '0 auto 10px', opacity: 0.4 }} />
          <div style={{ height: 16, background: theme.accent, borderRadius: 4, width: 50, margin: '0 auto' }} />
        </div>
      )}
      {preset.type === 'preset_features' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8, width: '100%' }}>
          {[1, 2, 3].map(i => (
            <div key={i} style={{ textAlign: 'center' }}>
              <div style={{ width: 16, height: 16, borderRadius: '50%', background: theme.accent, margin: '0 auto 4px', opacity: 0.6 }} />
              <div style={{ height: 4, background: theme.text, borderRadius: 1, width: '70%', margin: '0 auto 3px', opacity: 0.6 }} />
              <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '90%', margin: '0 auto', opacity: 0.2 }} />
            </div>
          ))}
        </div>
      )}
      {preset.type === 'preset_testimonials' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 6, width: '100%' }}>
          {[1, 2, 3].map(i => (
            <div key={i} style={{ background: theme.text + '10', borderRadius: 4, padding: 6 }}>
              <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '100%', marginBottom: 3, opacity: 0.3 }} />
              <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '60%', marginBottom: 6, opacity: 0.2 }} />
              <div style={{ display: 'flex', gap: 3, alignItems: 'center' }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: theme.accent, opacity: 0.5 }} />
                <div style={{ height: 3, background: theme.text, borderRadius: 1, width: 20, opacity: 0.4 }} />
              </div>
            </div>
          ))}
        </div>
      )}
      {preset.type === 'preset_pricing' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 6, width: '100%' }}>
          {[1, 2, 3].map(i => (
            <div key={i} style={{ background: i === 2 ? theme.accent : theme.text + '10', borderRadius: 4, padding: 6, textAlign: 'center' }}>
              <div style={{ height: 4, background: i === 2 ? '#fff' : theme.text, borderRadius: 1, width: '50%', margin: '0 auto 4px', opacity: i === 2 ? 0.9 : 0.4 }} />
              <div style={{ height: 8, background: i === 2 ? '#fff' : theme.text, borderRadius: 1, width: '40%', margin: '0 auto 4px', opacity: i === 2 ? 0.8 : 0.6 }} />
              <div style={{ height: 10, background: i === 2 ? '#fff' : theme.accent, borderRadius: 3, width: '60%', margin: '0 auto', opacity: 0.7 }} />
            </div>
          ))}
        </div>
      )}
      {/* FAQ */}
      {preset.type === 'preset_faq' && (
        <div style={{ width: '100%' }}>
          {[1, 2, 3].map(i => (
            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4, background: theme.text + '08', borderRadius: 3, padding: '4px 6px' }}>
              <div style={{ width: 8, height: 8, borderRadius: 2, background: theme.accent, opacity: 0.5 }} />
              <div style={{ height: 3, background: theme.text, borderRadius: 1, flex: 1, opacity: 0.4 }} />
            </div>
          ))}
        </div>
      )}
      {/* Contact */}
      {preset.type === 'preset_contact' && (
        <div style={{ display: 'flex', gap: 10, width: '100%' }}>
          <div style={{ flex: 1 }}>
            <div style={{ height: 6, background: theme.text, borderRadius: 1, width: '60%', marginBottom: 6, opacity: 0.7 }} />
            <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '90%', marginBottom: 3, opacity: 0.3 }} />
            <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '70%', opacity: 0.3 }} />
          </div>
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 4 }}>
            {[1, 2, 3].map(i => <div key={i} style={{ height: 10, background: theme.text + '15', borderRadius: 3 }} />)}
            <div style={{ height: 14, background: theme.accent, borderRadius: 3, width: '50%' }} />
          </div>
        </div>
      )}
      {/* Team */}
      {preset.type === 'preset_team' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8, width: '100%' }}>
          {[1, 2, 3].map(i => (
            <div key={i} style={{ textAlign: 'center' }}>
              <div style={{ width: 24, height: 24, borderRadius: '50%', background: theme.accent, margin: '0 auto 4px', opacity: 0.4 }} />
              <div style={{ height: 4, background: theme.text, borderRadius: 1, width: '60%', margin: '0 auto 2px', opacity: 0.6 }} />
              <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '80%', margin: '0 auto', opacity: 0.2 }} />
            </div>
          ))}
        </div>
      )}
      {/* Stats */}
      {preset.type === 'preset_stats' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: 6, width: '100%' }}>
          {['10K+', '99%', '50+', '24/7'].map((n, i) => (
            <div key={i} style={{ textAlign: 'center' }}>
              <div style={{ fontSize: 10, fontWeight: 700, color: theme.accent }}>{n}</div>
              <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '80%', margin: '3px auto 0', opacity: 0.3 }} />
            </div>
          ))}
        </div>
      )}
      {/* Portfolio */}
      {preset.type === 'preset_portfolio' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 4, width: '100%' }}>
          {[1, 2, 3, 4, 5, 6].map(i => (
            <div key={i} style={{ height: 28, borderRadius: 3, background: `hsl(${i * 50}, 60%, ${70 + i * 3}%)` }} />
          ))}
        </div>
      )}
      {/* Blog Grid */}
      {preset.type === 'preset_blog_grid' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 6, width: '100%' }}>
          {[1, 2, 3].map(i => (
            <div key={i} style={{ borderRadius: 4, border: `1px solid ${theme.text}20`, overflow: 'hidden' }}>
              <div style={{ height: 20, background: theme.accent + '30' }} />
              <div style={{ padding: 4 }}>
                <div style={{ height: 3, background: theme.text, borderRadius: 1, width: '80%', marginBottom: 2, opacity: 0.5 }} />
                <div style={{ height: 2, background: theme.text, borderRadius: 1, width: '60%', opacity: 0.2 }} />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export function PresetBrowser({ open, onClose, onSelectPreset, onAddBlank, siteId }: PresetBrowserProps) {
  const [search, setSearch] = useState('');
  const [tab, setTab] = useState<'presets' | 'saved'>('presets');
  const [savedTemplates, setSavedTemplates] = useState<BlockTemplate[]>([]);
  const [savedLoading, setSavedLoading] = useState(false);
  const insertSectionTemplate = useEditorStore(s => s.insertSectionTemplate);

  useEffect(() => {
    if (open && siteId && tab === 'saved') {
      setSavedLoading(true);
      api.get(`/sites/${siteId}/block-templates`).then(r => {
        setSavedTemplates(r.data.data || []);
      }).catch(() => {}).finally(() => setSavedLoading(false));
    }
  }, [open, siteId, tab]);

  if (!open) return null;

  const filtered = search
    ? presetsList.filter(p => p.label.toLowerCase().includes(search.toLowerCase()) || p.description.toLowerCase().includes(search.toLowerCase()))
    : presetsList;

  const filteredSaved = search
    ? savedTemplates.filter(t => t.name.toLowerCase().includes(search.toLowerCase()) || (t.description || '').toLowerCase().includes(search.toLowerCase()))
    : savedTemplates;

  const handleDeleteTemplate = async (id: string) => {
    if (!siteId) return;
    try {
      await api.delete(`/sites/${siteId}/block-templates/${id}`);
      setSavedTemplates(prev => prev.filter(t => t.id !== id));
    } catch { /* ignore */ }
  };

  const handleInsertTemplate = (tpl: BlockTemplate) => {
    insertSectionTemplate(tpl.blocks_data);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-base-100 rounded-xl shadow-2xl w-[640px] max-w-[90vw] max-h-[80vh] flex flex-col" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-3 border-b border-base-300/20">
          <div className="flex items-center gap-2">
            <Sparkles size={16} className="text-purple-500" />
            <h2 className="text-sm font-semibold">Add Section</h2>
          </div>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square">
            <X size={16} />
          </button>
        </div>

        {/* Tabs + Search */}
        <div className="px-5 py-2 border-b border-base-300/10 space-y-2">
          <div className="flex items-center gap-1">
            <button onClick={() => setTab('presets')}
              className={`text-[11px] font-medium px-2.5 py-1 rounded-md transition-colors ${tab === 'presets' ? 'bg-primary/10 text-primary' : 'text-base-content/40 hover:text-base-content/60'}`}>
              <Sparkles size={11} className="inline mr-1" />Presets
            </button>
            {siteId && (
              <button onClick={() => setTab('saved')}
                className={`text-[11px] font-medium px-2.5 py-1 rounded-md transition-colors ${tab === 'saved' ? 'bg-primary/10 text-primary' : 'text-base-content/40 hover:text-base-content/60'}`}>
                <BookMarked size={11} className="inline mr-1" />Saved{savedTemplates.length > 0 ? ` (${savedTemplates.length})` : ''}
              </button>
            )}
          </div>
          <div className="relative">
            <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/30" />
            <input
              type="text"
              placeholder={tab === 'presets' ? 'Search sections...' : 'Search saved templates...'}
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="input input-bordered input-sm w-full pl-8 text-[12px]"
              autoFocus
            />
          </div>
        </div>

        {/* Grid */}
        <div className="flex-1 overflow-y-auto p-5">
          {tab === 'presets' && (
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
              {/* Blank section */}
              <button
                onClick={() => { onAddBlank(); onClose(); }}
                className="group border-2 border-dashed border-base-300/30 hover:border-blue-400 rounded-xl p-3 text-left transition-all hover:shadow-md"
              >
                <div style={{ height: 120, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                  <PanelTop size={24} className="text-base-content/20 group-hover:text-blue-400 transition-colors" />
                </div>
                <div className="mt-2">
                  <div className="text-[11px] font-semibold text-base-content/70 group-hover:text-blue-600">Blank Section</div>
                  <div className="text-[9px] text-base-content/30">Empty section to build from scratch</div>
                </div>
              </button>

              {/* Presets */}
              {filtered.map(preset => (
                <button
                  key={preset.type}
                  onClick={() => { onSelectPreset(preset.type); onClose(); }}
                  className="group border border-base-300/20 hover:border-purple-400 rounded-xl p-3 text-left transition-all hover:shadow-md"
                >
                  <PresetThumbnail preset={preset} />
                  <div className="mt-2">
                    <div className="flex items-center gap-1.5">
                      <BlockIcon icon={preset.icon} size={12} className="text-purple-400" />
                      <span className="text-[11px] font-semibold text-base-content/70 group-hover:text-purple-600">{preset.label}</span>
                    </div>
                    <div className="text-[9px] text-base-content/30 mt-0.5">{preset.description}</div>
                  </div>
                </button>
              ))}

              {filtered.length === 0 && search && (
                <div className="col-span-full text-center py-8 text-base-content/30 text-sm">
                  No sections match "{search}"
                </div>
              )}
            </div>
          )}

          {tab === 'saved' && (
            <div>
              {savedLoading && (
                <div className="flex items-center justify-center py-12">
                  <span className="loading loading-spinner loading-sm text-base-content/20"></span>
                </div>
              )}

              {!savedLoading && filteredSaved.length === 0 && (
                <div className="text-center py-12">
                  <BookMarked size={28} className="mx-auto text-base-content/15 mb-2" />
                  <div className="text-sm text-base-content/40">
                    {search ? `No saved templates match "${search}"` : 'No saved templates yet'}
                  </div>
                  <div className="text-[11px] text-base-content/25 mt-1">
                    Save sections from the block toolbar to reuse them here.
                  </div>
                </div>
              )}

              {!savedLoading && filteredSaved.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  {filteredSaved.map(tpl => (
                    <div key={tpl.id}
                      className="group border border-base-300/20 hover:border-primary/40 rounded-xl p-3 text-left transition-all hover:shadow-md relative">
                      <button onClick={() => handleInsertTemplate(tpl)} className="w-full text-left">
                        {/* Mini wireframe preview */}
                        <div className="bg-base-200/50 rounded-lg p-2 h-[100px] overflow-hidden flex flex-col gap-1">
                          {tpl.blocks_data.slice(0, 3).map((block: any, i: number) => (
                            <SavedBlockPreview key={i} block={block} />
                          ))}
                          {tpl.blocks_data.length > 3 && (
                            <div className="text-[8px] text-base-content/25 text-center">+{tpl.blocks_data.length - 3} more</div>
                          )}
                        </div>
                        <div className="mt-2">
                          <div className="text-[11px] font-semibold text-base-content/70 group-hover:text-primary truncate">{tpl.name}</div>
                          <div className="flex items-center gap-1.5 mt-0.5">
                            <span className="text-[9px] px-1.5 py-0.5 rounded bg-base-200 text-base-content/40">{tpl.category}</span>
                            {tpl.is_system && <span className="text-[9px] px-1.5 py-0.5 rounded bg-primary/10 text-primary/60">system</span>}
                          </div>
                          {tpl.description && <div className="text-[9px] text-base-content/30 mt-0.5 truncate">{tpl.description}</div>}
                        </div>
                      </button>
                      {/* Delete button */}
                      {!tpl.is_system && (
                        <button
                          onClick={(e) => { e.stopPropagation(); handleDeleteTemplate(tpl.id); }}
                          className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity btn btn-ghost btn-xs btn-square text-error/50 hover:text-error"
                          title="Delete template"
                        >
                          <Trash2 size={12} />
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/** Simple wireframe preview of a saved block tree */
function SavedBlockPreview({ block }: { block: any }) {
  const type = block.type || 'unknown';
  const children = block.children || [];

  if (type === 'section') {
    return (
      <div className="border border-base-300/20 rounded p-1 space-y-0.5">
        {children.slice(0, 2).map((child: any, i: number) => (
          <SavedBlockPreview key={i} block={child} />
        ))}
      </div>
    );
  }
  if (type === 'row') {
    return (
      <div className="flex gap-0.5">
        {children.map((child: any, i: number) => (
          <div key={i} className="flex-1">
            <SavedBlockPreview block={child} />
          </div>
        ))}
      </div>
    );
  }
  if (type === 'column') {
    return (
      <div className="space-y-0.5 p-0.5">
        {children.slice(0, 3).map((child: any, i: number) => (
          <SavedBlockPreview key={i} block={child} />
        ))}
      </div>
    );
  }
  // Module-level blocks
  const colors: Record<string, string> = {
    heading: 'bg-base-content/15 h-2',
    paragraph: 'bg-base-content/8 h-1.5',
    'rich-text': 'bg-base-content/8 h-1.5',
    button: 'bg-primary/30 h-2 w-1/3',
    image: 'bg-base-content/10 h-4',
    hero: 'bg-primary/10 h-5',
    gallery: 'bg-base-content/10 h-3',
    'contact-form': 'bg-base-300/40 h-3',
  };
  const cls = colors[type] || 'bg-base-content/8 h-1.5';
  return <div className={`rounded ${cls}`} />;
}
