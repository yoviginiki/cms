import { DragOverlay as DndDragOverlay } from '@dnd-kit/core';
import type { Active } from '@dnd-kit/core';
import { blockRegistry } from '@/components/blocks/registry';
import type { BlockData } from '@/types/blocks';

interface DragOverlayProps {
  active: Active | null;
}

export function DragOverlay({ active }: DragOverlayProps) {
  if (!active) return null;

  const data = active.data.current;

  // Dragging from sidebar (new block)
  if (data?.type === 'new-block') {
    const reg = blockRegistry.get(data.blockType as string);
    return (
      <DndDragOverlay>
        <div className="bg-white border-2 border-blue-400 rounded-lg p-4 shadow-xl opacity-80 w-64">
          <span className="text-sm font-medium text-blue-600">
            {reg?.definition.label ?? data.blockType}
          </span>
        </div>
      </DndDragOverlay>
    );
  }

  // Dragging existing block
  if (data?.type === 'block') {
    const block = data.block as BlockData;
    const reg = blockRegistry.get(block.type);
    return (
      <DndDragOverlay>
        <div className="bg-white border-2 border-blue-400 rounded-lg shadow-xl opacity-80 max-w-md overflow-hidden">
          {reg ? (
            <reg.Preview
              block={block}
              isSelected={false}
              onUpdate={() => {}}
              onSelect={() => {}}
            />
          ) : (
            <div className="p-4 text-sm text-gray-500">{block.type}</div>
          )}
        </div>
      </DndDragOverlay>
    );
  }

  return null;
}
