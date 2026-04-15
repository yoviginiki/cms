import { useState, useMemo } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { Square, Search } from 'lucide-react';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import type { BlockCategory, BlockDefinition } from '@/types/blocks';

const CATEGORY_ORDER: BlockCategory[] = [
  'layout',
  'content',
  'media',
  'interactive',
  'commerce',
  'forms',
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
      className={`flex w-full items-center gap-2 p-2 rounded hover:bg-gray-100 cursor-grab border border-transparent hover:border-gray-200 text-left ${
        isDragging ? 'opacity-50' : ''
      }`}
    >
      <Square className="h-4 w-4 shrink-0 text-gray-500" />
      <span className="text-sm text-gray-700">{definition.label}</span>
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
      if (search && !def.label.toLowerCase().includes(search.toLowerCase())) {
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
      <div className="p-3 border-b border-gray-200">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search blocks..."
            className="w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>
      <div className="flex-1 overflow-y-auto p-3 space-y-4">
        {CATEGORY_ORDER.map((category) => {
          const definitions = grouped.get(category);
          if (!definitions || definitions.length === 0) return null;

          return (
            <div key={category}>
              <h3 className="text-xs font-semibold uppercase text-gray-500 mb-2">
                {category}
              </h3>
              <div className="space-y-0.5">
                {definitions.map((def) => (
                  <DraggableBlockItem key={def.type} definition={def} />
                ))}
              </div>
            </div>
          );
        })}
        {grouped.size === 0 && (
          <p className="text-sm text-gray-400 text-center py-4">No blocks found</p>
        )}
      </div>
    </div>
  );
}
