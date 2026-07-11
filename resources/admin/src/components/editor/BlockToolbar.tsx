import { useState } from 'react';
import { ArrowUp, ArrowDown, Copy, Trash2, GripVertical, Save } from 'lucide-react';
import { useParams } from 'react-router-dom';
import type { BlockData } from '@/types/blocks';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import { BlockIcon } from './BlockIcon';
import { SaveToLibraryDialog } from './SaveToLibraryDialog';

interface BlockToolbarProps {
  block: BlockData;
  dragHandleProps: Record<string, unknown>;
}

export function BlockToolbar({ block, dragHandleProps }: BlockToolbarProps) {
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const duplicateBlock = useEditorStore((s) => s.duplicateBlock);
  const moveBlock = useEditorStore((s) => s.moveBlock);
  const blocks = useEditorStore((s) => s.blocks);
  const { siteId = '' } = useParams();
  const [saveOpen, setSaveOpen] = useState(false);

  const reg = blockRegistry.get(block.type);
  const label = reg?.definition.label ?? block.type;

  // Find siblings to determine if we can move up/down
  function findSiblings(): BlockData[] {
    function search(list: BlockData[]): BlockData[] | null {
      for (const b of list) {
        if (b.id === block.id) return list;
        const found = search(b.children);
        if (found) return found;
      }
      return null;
    }
    return search(blocks) ?? blocks;
  }

  const siblings = findSiblings();
  const idx = siblings.findIndex((b) => b.id === block.id);
  const canMoveUp = idx > 0;
  const canMoveDown = idx < siblings.length - 1;

  return (
    <div className="absolute -top-10 left-0 z-20 flex items-center gap-1 bg-blue-500 text-white rounded-t-md px-2 py-1 text-xs shadow-lg">
      <button
        className="cursor-grab hover:bg-blue-600 rounded p-0.5"
        {...(dragHandleProps as React.HTMLAttributes<HTMLButtonElement>)}
      >
        <GripVertical size={14} />
      </button>

      <BlockIcon icon={reg?.definition.icon ?? 'Box'} size={12} className="opacity-80" />
      <span className="font-medium px-1">{label}</span>

      <div className="w-px h-4 bg-blue-400" />

      <button
        className="hover:bg-blue-600 rounded p-0.5 disabled:opacity-40"
        disabled={!canMoveUp}
        onClick={(e) => {
          e.stopPropagation();
          if (canMoveUp) moveBlock(block.id, siblings[idx - 1].id, 'before');
        }}
        title="Move up"
      >
        <ArrowUp size={14} />
      </button>

      <button
        className="hover:bg-blue-600 rounded p-0.5 disabled:opacity-40"
        disabled={!canMoveDown}
        onClick={(e) => {
          e.stopPropagation();
          if (canMoveDown) moveBlock(block.id, siblings[idx + 1].id, 'after');
        }}
        title="Move down"
      >
        <ArrowDown size={14} />
      </button>

      <button
        className="hover:bg-blue-600 rounded p-0.5"
        onClick={(e) => {
          e.stopPropagation();
          duplicateBlock(block.id);
        }}
        title="Duplicate"
      >
        <Copy size={14} />
      </button>

      <button
        className="hover:bg-blue-600 rounded p-0.5"
        onClick={(e) => {
          e.stopPropagation();
          if (siteId) setSaveOpen(true);
        }}
        title="Save to Library"
      >
        <Save size={14} />
      </button>

      {saveOpen && (
        <SaveToLibraryDialog
          open={saveOpen}
          onClose={() => setSaveOpen(false)}
          siteId={siteId}
          blocks={[block]}
          defaultName={label}
        />
      )}

      <button
        className="hover:bg-red-500 rounded p-0.5"
        onClick={(e) => {
          e.stopPropagation();
          removeBlock(block.id);
        }}
        title="Delete"
      >
        <Trash2 size={14} />
      </button>
    </div>
  );
}
