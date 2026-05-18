import { useState, useMemo } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { Search } from 'lucide-react';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import { BlockIcon } from './BlockIcon';
import type { BlockCategory, BlockDefinition } from '@/types/blocks';

const CATEGORY_ORDER: { key: BlockCategory; label: string }[] = [
  { key: 'dynamic', label: 'Dynamic Content' },
  { key: 'layout', label: 'Layout' },
  { key: 'typography', label: 'Typography' },
  { key: 'content', label: 'Content' },
  { key: 'navigation', label: 'Navigation' },
  { key: 'media', label: 'Media' },
  { key: 'blog', label: 'Blog & Editorial' },
  { key: 'interactive', label: 'Interactive' },
  { key: 'data', label: 'Data & Content' },
  { key: 'commerce', label: 'Commerce' },
  { key: 'forms', label: 'Forms' },
  { key: 'embed', label: 'Embeds' },
  { key: 'marketing', label: 'Marketing' },
  { key: 'advanced', label: 'Advanced' },
];

function DraggableBlockItem({ definition }: { definition: BlockDefinition }) {
  const addBlock = useEditorStore((s) => s.addBlock);
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `picker-${definition.type}`,
    data: { type: 'new-block', blockType: definition.type },
  });

  return (
    <button
      ref={setNodeRef}
      {...attributes}
      {...listeners}
      onClick={() => addBlock(definition.type)}
      className={`flex flex-col items-center gap-1 px-1 py-2.5 rounded-lg text-center transition-colors
        hover:bg-blue-50 hover:text-blue-600 cursor-grab border border-transparent hover:border-blue-200
        ${isDragging ? 'opacity-40' : ''}`}
    >
      <BlockIcon icon={definition.icon} size={20} className="text-gray-500" />
      <span className="text-[10px] leading-tight text-gray-600 truncate w-full">
        {definition.label}
      </span>
    </button>
  );
}

export function BlockPicker() {
  const [search, setSearch] = useState('');

  const grouped = useMemo(() => {
    const all = blockRegistry.getAll();
    const groups = new Map<BlockCategory, BlockDefinition[]>();

    for (const reg of all.values()) {
      const def = reg.definition;
      if (search && !def.label.toLowerCase().includes(search.toLowerCase()) && !def.type.toLowerCase().includes(search.toLowerCase())) {
        continue;
      }
      if (!groups.has(def.category)) {
        groups.set(def.category, []);
      }
      groups.get(def.category)!.push(def);
    }

    return groups;
  }, [search]);

  return (
    <div className="flex flex-col h-full">
      <div className="p-2 border-b border-base-300/20">
        <label className="input input-bordered input-sm flex items-center gap-2 text-[12px]">
          <Search className="h-3.5 w-3.5 text-base-content/30" />
          <input type="text" id="block-picker-search" name="block-picker-search" value={search} onChange={(e) => setSearch(e.target.value)}
            placeholder="Search blocks..." className="grow bg-transparent" aria-label="Search blocks" />
        </label>
      </div>
      <div className="flex-1 overflow-y-auto p-2 space-y-3">
        {CATEGORY_ORDER.map(({ key, label }) => {
          const definitions = grouped.get(key);
          if (!definitions || definitions.length === 0) return null;

          return (
            <div key={key}>
              <h3 className="text-[9px] font-semibold uppercase tracking-wider text-base-content/30 mb-1 px-1">
                {label} <span className="text-base-content/15">({definitions.length})</span>
              </h3>
              <div className="grid grid-cols-3 gap-0.5">
                {definitions.map((def) => (
                  <DraggableBlockItem key={def.type} definition={def} />
                ))}
              </div>
            </div>
          );
        })}

        {/* Show any blocks in categories not listed above */}
        {Array.from(grouped.entries())
          .filter(([cat]) => !CATEGORY_ORDER.some(c => c.key === cat))
          .map(([cat, defs]) => (
            <div key={cat}>
              <h3 className="text-[9px] font-semibold uppercase tracking-wider text-base-content/30 mb-1 px-1">
                {cat} <span className="text-base-content/15">({defs.length})</span>
              </h3>
              <div className="grid grid-cols-3 gap-0.5">
                {defs.map((def) => (
                  <DraggableBlockItem key={def.type} definition={def} />
                ))}
              </div>
            </div>
          ))
        }

        {grouped.size === 0 && (
          <p className="text-[12px] text-base-content/30 text-center py-8">No blocks found</p>
        )}
      </div>
    </div>
  );
}
