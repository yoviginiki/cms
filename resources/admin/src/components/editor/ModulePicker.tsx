import { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Search, X } from 'lucide-react';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import { BlockIcon } from './BlockIcon';
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
  anchorEl: HTMLElement | null;
}

export function ModulePicker({ parentId, insertIndex, onClose, anchorEl }: ModulePickerProps) {
  const [search, setSearch] = useState('');
  const addBlock = useEditorStore((s) => s.addBlock);
  const pickerRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  // Position the popover near the anchor
  const [pos, setPos] = useState({ top: 0, left: 0 });

  useEffect(() => {
    if (anchorEl) {
      const rect = anchorEl.getBoundingClientRect();
      setPos({
        top: rect.bottom + 4,
        left: Math.max(8, rect.left - 120),
      });
    }
    searchRef.current?.focus();
  }, [anchorEl]);

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
    <div
      ref={pickerRef}
      className="fixed z-50 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden"
      style={{ top: pos.top, left: pos.left, width: 320, maxHeight: 420 }}
      onClick={(e) => e.stopPropagation()}
    >
      {/* Header */}
      <div className="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
        <Search size={14} className="text-gray-400 shrink-0" />
        <input
          ref={searchRef}
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search modules..."
          className="flex-1 text-sm bg-transparent outline-none placeholder-gray-400"
        />
        <button onClick={onClose} className="p-0.5 text-gray-400 hover:text-gray-600">
          <X size={14} />
        </button>
      </div>

      {/* Module grid */}
      <div className="overflow-y-auto p-2" style={{ maxHeight: 370 }}>
        {CATEGORY_ORDER.map(({ key, label }) => {
          const defs = grouped.get(key);
          if (!defs?.length) return null;

          return (
            <div key={key} className="mb-3">
              <h4 className="text-[9px] font-semibold uppercase tracking-wider text-gray-400 px-1 mb-1">
                {label}
              </h4>
              <div className="grid grid-cols-3 gap-1">
                {defs.map((def) => (
                  <button
                    key={def.type}
                    onClick={() => handleSelect(def.type)}
                    className="flex flex-col items-center gap-1 px-2 py-2.5 rounded-lg text-center transition-colors hover:bg-blue-50 hover:text-blue-600 border border-transparent hover:border-blue-200"
                  >
                    <BlockIcon icon={def.icon} size={20} className="text-gray-500" />
                    <span className="text-[10px] leading-tight text-gray-600 truncate w-full">
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
            <div key={cat} className="mb-3">
              <h4 className="text-[9px] font-semibold uppercase tracking-wider text-gray-400 px-1 mb-1">
                {cat}
              </h4>
              <div className="grid grid-cols-3 gap-1">
                {defs.map((def) => (
                  <button
                    key={def.type}
                    onClick={() => handleSelect(def.type)}
                    className="flex flex-col items-center gap-1 px-2 py-2.5 rounded-lg text-center transition-colors hover:bg-blue-50 hover:text-blue-600 border border-transparent hover:border-blue-200"
                  >
                    <BlockIcon icon={def.icon} size={20} className="text-gray-500" />
                    <span className="text-[10px] leading-tight text-gray-600 truncate w-full">
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
    </div>,
    document.body
  );
}
