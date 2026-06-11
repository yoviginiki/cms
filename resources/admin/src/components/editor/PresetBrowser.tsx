/**
 * P4 — Visual Preset Browser with preview thumbnails.
 * Shows available section presets in a modal/dropdown with visual previews.
 */
import { useState } from 'react';
import { X, Search, PanelTop, Sparkles } from 'lucide-react';
import { presets as presetsList, type PresetDefinition } from '@/presets';
import { BlockIcon } from './BlockIcon';

interface PresetBrowserProps {
  open: boolean;
  onClose: () => void;
  onSelectPreset: (type: string) => void;
  onAddBlank: () => void;
}

// Color themes for visual thumbnails
const PRESET_THEMES: Record<string, { bg: string; accent: string; text: string }> = {
  preset_hero: { bg: '#1e293b', accent: '#3b82f6', text: '#f8fafc' },
  preset_cta: { bg: '#7c3aed', accent: '#a78bfa', text: '#ffffff' },
  preset_features: { bg: '#f8fafc', accent: '#10b981', text: '#1e293b' },
  preset_testimonials: { bg: '#fefce8', accent: '#f59e0b', text: '#78350f' },
  preset_pricing: { bg: '#f0fdf4', accent: '#22c55e', text: '#14532d' },
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
    </div>
  );
}

export function PresetBrowser({ open, onClose, onSelectPreset, onAddBlank }: PresetBrowserProps) {
  const [search, setSearch] = useState('');

  if (!open) return null;

  const filtered = search
    ? presetsList.filter(p => p.label.toLowerCase().includes(search.toLowerCase()) || p.description.toLowerCase().includes(search.toLowerCase()))
    : presetsList;

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

        {/* Search */}
        <div className="px-5 py-2 border-b border-base-300/10">
          <div className="relative">
            <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/30" />
            <input
              type="text"
              placeholder="Search sections..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="input input-bordered input-sm w-full pl-8 text-[12px]"
              autoFocus
            />
          </div>
        </div>

        {/* Grid */}
        <div className="flex-1 overflow-y-auto p-5">
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
          </div>

          {filtered.length === 0 && search && (
            <div className="text-center py-8 text-base-content/30 text-sm">
              No sections match "{search}"
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
