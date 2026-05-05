import { Eye, EyeOff, Lock, Unlock, GripVertical } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import type { BlockData } from '@/types/blocks';

export function LayersPanel() {
  const blocks = useEditorStore(s => s.blocks);
  const selectedBlockId = useEditorStore(s => s.selectedBlockId);
  const selectBlock = useEditorStore(s => s.selectBlock);
  const updateBlock = useEditorStore(s => s.updateBlock);

  // Sort by zIndex descending (top layer first)
  const sorted = [...blocks].sort((a, b) => {
    const az = (a.style?.layout?.zIndex as number) ?? 0;
    const bz = (b.style?.layout?.zIndex as number) ?? 0;
    return bz - az;
  });

  const toggleVisibility = (block: BlockData) => {
    const style = block.style || {};
    const layout = style.layout || {};
    const isHidden = layout.display === 'none';
    updateBlock(block.id, {
      __style: { ...style, layout: { ...layout, display: isHidden ? 'block' : 'none' } },
    } as any);
  };

  const toggleLock = (block: BlockData) => {
    const style = block.style || {};
    const layout = style.layout || {};
    updateBlock(block.id, {
      __style: { ...style, layout: { ...layout, locked: !layout.locked } },
    } as any);
  };

  if (blocks.length === 0) {
    return (
      <div className="flex items-center justify-center h-full text-[12px] text-base-content/25">
        No blocks on canvas
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between p-3 border-b border-base-300/20">
        <h3 className="text-[12px] font-medium text-base-content/60">Layers</h3>
        <span className="text-[10px] text-base-content/30">{blocks.length}</span>
      </div>
      <div className="flex-1 overflow-y-auto">
        {sorted.map(block => {
          const reg = blockRegistry.get(block.type);
          const isSelected = block.id === selectedBlockId;
          const isHidden = block.style?.layout?.display === 'none';
          const isLocked = block.style?.layout?.locked === true;
          const z = (block.style?.layout?.zIndex as number) ?? 0;

          // Get a preview label
          let label = reg?.definition.label || block.type;
          if (block.type === 'text' && block.data.content) {
            const stripped = String(block.data.content).replace(/<[^>]+>/g, '').trim();
            if (stripped) label = stripped.slice(0, 30) + (stripped.length > 30 ? '...' : '');
          }
          if (block.type === 'heading' && block.data.text) {
            label = String(block.data.text).slice(0, 30);
          }
          if (block.type === 'image' && block.data.url) {
            label = 'Image';
          }

          return (
            <div
              key={block.id}
              className={`flex items-center gap-1.5 px-2 py-1.5 text-[11px] cursor-pointer border-l-2 transition-colors ${
                isSelected
                  ? 'border-l-primary bg-primary/5 text-base-content/90'
                  : 'border-l-transparent hover:bg-base-300/10 text-base-content/50'
              } ${isHidden ? 'opacity-40' : ''}`}
              onClick={() => selectBlock(block.id)}
            >
              <GripVertical size={10} className="text-base-content/15 shrink-0 cursor-grab" />

              {/* Type icon */}
              <span className="text-[9px] font-mono text-base-content/25 w-3 shrink-0">{z}</span>

              {/* Label */}
              <span className="flex-1 truncate min-w-0">{label}</span>

              {/* Controls */}
              <button onClick={e => { e.stopPropagation(); toggleVisibility(block); }}
                className="btn btn-ghost btn-xs btn-square opacity-40 hover:opacity-100" title={isHidden ? 'Show' : 'Hide'}>
                {isHidden ? <EyeOff size={11} /> : <Eye size={11} />}
              </button>
              <button onClick={e => { e.stopPropagation(); toggleLock(block); }}
                className={`btn btn-ghost btn-xs btn-square ${isLocked ? 'text-warning opacity-80' : 'opacity-30 hover:opacity-100'}`}
                title={isLocked ? 'Unlock' : 'Lock'}>
                {isLocked ? <Lock size={11} /> : <Unlock size={11} />}
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}
