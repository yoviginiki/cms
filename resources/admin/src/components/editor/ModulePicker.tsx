import { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Search, X, Sparkles } from 'lucide-react';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import { BlockIcon } from './BlockIcon';
import { presets } from '@/presets';
import type { BlockDefinition, BlockCategory } from '@/types/blocks';

const CATEGORY_ORDER: { key: BlockCategory; label: string }[] = [
  { key: 'typography', label: 'Typography' },
  { key: 'content', label: 'Content' },
  { key: 'media', label: 'Media' },
  { key: 'navigation', label: 'Navigation' },
  { key: 'blog', label: 'Blog' },
  { key: 'interactive', label: 'Interactive' },
  { key: 'data', label: 'Data' },
  { key: 'commerce', label: 'Commerce' },
  { key: 'forms', label: 'Forms' },
  { key: 'embed', label: 'Embeds' },
  { key: 'marketing', label: 'Marketing' },
  { key: 'advanced', label: 'Advanced' },
];

interface ModulePickerProps {
  parentId: string;
  insertIndex?: number;
  onClose: () => void;
  anchorEl?: HTMLElement | null;
}

export function ModulePicker({ parentId, insertIndex, onClose }: ModulePickerProps) {
  const [search, setSearch] = useState('');
  const addBlock = useEditorStore((s) => s.addBlock);
  const addPreset = useEditorStore((s) => s.addPreset);
  const pickerRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    searchRef.current?.focus();
  }, []);

  // Close on click-outside or Escape
  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (pickerRef.current && !pickerRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('mousedown', handleClick);
    document.addEventListener('keydown', handleKey);
    return () => {
      document.removeEventListener('mousedown', handleClick);
      document.removeEventListener('keydown', handleKey);
    };
  }, [onClose]);

  // Get module-level blocks only (exclude section, row, column)
  const modules: BlockDefinition[] = [];
  for (const reg of blockRegistry.getAll().values()) {
    const def = reg.definition;
    if (def.level === 'section' || def.level === 'row' || def.level === 'column') continue;
    if (def.type === 'section' || def.type === 'row' || def.type === 'column') continue;
    if (search && !def.label.toLowerCase().includes(search.toLowerCase()) && !def.type.toLowerCase().includes(search.toLowerCase())) continue;
    modules.push(def);
  }

  // Group by category
  const grouped = new Map<BlockCategory, BlockDefinition[]>();
  for (const def of modules) {
    if (!grouped.has(def.category)) grouped.set(def.category, []);
    grouped.get(def.category)!.push(def);
  }

  const handleSelect = (type: string) => {
    addBlock(type, parentId, insertIndex);
    onClose();
  };

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30" onClick={onClose}>
    <div
      ref={pickerRef}
      className="bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden w-[480px] max-h-[520px]"
      onClick={(e) => e.stopPropagation()}
    >
      {/* Header */}
      <div className="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
        <Search size={14} className="text-gray-400 shrink-0" />
        <input
          ref={searchRef}
          id="module-picker-search"
          name="module-picker-search"
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search modules..."
          className="flex-1 text-sm bg-transparent outline-none placeholder-gray-400"
          aria-label="Search modules"
        />
        <button onClick={onClose} className="p-0.5 text-gray-400 hover:text-gray-600">
          <X size={14} />
        </button>
      </div>

      {/* Module grid */}
      <div className="overflow-y-auto p-3" style={{ maxHeight: 460 }}>
        {/* Presets section */}
        {!search && (
          <div className="mb-4 pb-3 border-b border-gray-100">
            <h4 className="text-[10px] font-semibold uppercase tracking-wider text-purple-500 px-1 mb-2 flex items-center gap-1">
              <Sparkles size={10} /> Presets
            </h4>
            <div className="grid grid-cols-2 gap-2">
              {presets.map((preset) => (
                <button
                  key={preset.type}
                  onClick={() => { addPreset(preset.type); onClose(); }}
                  className="flex items-start gap-2 px-3 py-2.5 rounded-lg text-left transition-colors hover:bg-purple-50 hover:text-purple-700 border border-transparent hover:border-purple-200"
                >
                  <BlockIcon icon={preset.icon} size={18} className="text-purple-400 mt-0.5 shrink-0" />
                  <div>
                    <span className="text-[11px] font-medium text-gray-700 block">{preset.label}</span>
                    <span className="text-[9px] text-gray-400 leading-tight">{preset.description}</span>
                  </div>
                </button>
              ))}
            </div>
          </div>
        )}

        {CATEGORY_ORDER.map(({ key, label }) => {
          const defs = grouped.get(key);
          if (!defs?.length) return null;

          return (
            <div key={key} className="mb-4">
              <h4 className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 px-1 mb-1.5">
                {label}
              </h4>
              <div className="grid grid-cols-4 gap-1.5">
                {defs.map((def) => (
                  <button
                    key={def.type}
                    onClick={() => handleSelect(def.type)}
                    className="flex flex-col items-center gap-1.5 px-2 py-3 rounded-lg text-center transition-colors hover:bg-blue-50 hover:text-blue-600 border border-transparent hover:border-blue-200"
                  >
                    <BlockIcon icon={def.icon} size={22} className="text-gray-500" />
                    <span className="text-[11px] leading-tight text-gray-600 truncate w-full">
                      {def.label}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          );
        })}

        {/* Uncategorized */}
        {Array.from(grouped.entries())
          .filter(([cat]) => !CATEGORY_ORDER.some(c => c.key === cat))
          .map(([cat, defs]) => (
            <div key={cat} className="mb-4">
              <h4 className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 px-1 mb-1.5">
                {cat}
              </h4>
              <div className="grid grid-cols-4 gap-1.5">
                {defs.map((def) => (
                  <button
                    key={def.type}
                    onClick={() => handleSelect(def.type)}
                    className="flex flex-col items-center gap-1.5 px-2 py-3 rounded-lg text-center transition-colors hover:bg-blue-50 hover:text-blue-600 border border-transparent hover:border-blue-200"
                  >
                    <BlockIcon icon={def.icon} size={22} className="text-gray-500" />
                    <span className="text-[11px] leading-tight text-gray-600 truncate w-full">
                      {def.label}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          ))
        }

        {modules.length === 0 && (
          <p className="text-xs text-gray-400 text-center py-6">No modules found</p>
        )}
      </div>
    </div>
    </div>,
    document.body
  );
}
