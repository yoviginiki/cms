import { useEffect, useState } from 'react';
import {
  Copy, ClipboardPaste, CopyPlus, Paintbrush, Clipboard, BookMarked, Trash2,
} from 'lucide-react';
import { useEditorStore, type StylePart } from '@/stores/editorStore';
import type { BlockData } from '@/types/blocks';
import { SaveToLibraryDialog } from './SaveToLibraryDialog';

const STYLE_PARTS: { key: StylePart; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'typography', label: 'Type' },
  { key: 'spacing', label: 'Spacing' },
  { key: 'colors', label: 'Colors' },
  { key: 'borders', label: 'Borders' },
];

/**
 * Right-click context menu for a block (P4 editor ergonomics). Wires the store's
 * block clipboard + the new style clipboard (Copy/Paste Style with granularity),
 * plus Duplicate / Save to Library / Delete. Positioned at the cursor; closes on
 * click-away or Escape.
 */
export function BlockContextMenu({ block, x, y, siteId, onClose }: {
  block: BlockData;
  x: number;
  y: number;
  siteId: string;
  onClose: () => void;
}) {
  const duplicateBlock = useEditorStore((s) => s.duplicateBlock);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const copyBlock = useEditorStore((s) => s.copyBlock);
  const pasteBlock = useEditorStore((s) => s.pasteBlock);
  const copyStyle = useEditorStore((s) => s.copyStyle);
  const pasteStyle = useEditorStore((s) => s.pasteStyle);
  const clipboard = useEditorStore((s) => s.clipboard);
  const styleClipboard = useEditorStore((s) => s.styleClipboard);

  const [saveOpen, setSaveOpen] = useState(false);

  useEffect(() => {
    const close = () => onClose();
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('click', close);
    window.addEventListener('keydown', onKey);
    return () => { window.removeEventListener('click', close); window.removeEventListener('keydown', onKey); };
  }, [onClose]);

  const run = (fn: () => void) => (e: React.MouseEvent) => { e.stopPropagation(); fn(); onClose(); };

  // keep the menu on-screen
  const style: React.CSSProperties = {
    position: 'fixed', top: Math.min(y, window.innerHeight - 320), left: Math.min(x, window.innerWidth - 220), zIndex: 1000,
  };

  return (
    <>
      <div style={style} onClick={(e) => e.stopPropagation()}
        className="w-52 bg-base-100 border border-base-300 shadow-xl text-[12px] py-1 select-none">
        <Item icon={CopyPlus} label="Duplicate" hint="⌘D" onClick={run(() => duplicateBlock(block.id))} />
        <Item icon={Copy} label="Copy" hint="⌘C" onClick={run(() => copyBlock(block.id))} />
        <Item icon={ClipboardPaste} label="Paste" hint="⌘V" disabled={!clipboard} onClick={run(() => pasteBlock(block.id))} />
        <Divider />
        <Item icon={Paintbrush} label="Copy style" onClick={run(() => copyStyle(block.id))} />
        <div className="px-2 py-1">
          <div className="flex items-center gap-1.5 text-base-content/50 mb-1"><Clipboard size={12} /> Paste style</div>
          <div className="flex flex-wrap gap-1 pl-1">
            {STYLE_PARTS.map((p) => (
              <button key={p.key} disabled={!styleClipboard}
                onClick={run(() => pasteStyle(block.id, p.key))}
                className="text-[10px] px-1.5 py-0.5 border border-base-300 hover:border-primary hover:text-primary disabled:opacity-30 disabled:hover:border-base-300 disabled:hover:text-base-content">
                {p.label}
              </button>
            ))}
          </div>
        </div>
        <Divider />
        <Item icon={BookMarked} label="Save to Library…" onClick={(e) => { e.stopPropagation(); setSaveOpen(true); }} />
        <Divider />
        <Item icon={Trash2} label="Delete" hint="⌫" danger onClick={run(() => removeBlock(block.id))} />
      </div>

      {saveOpen && (
        <SaveToLibraryDialog open={saveOpen} onClose={() => { setSaveOpen(false); onClose(); }}
          siteId={siteId} blocks={[block]} defaultName={block.type} />
      )}
    </>
  );
}

function Item({ icon: Icon, label, hint, onClick, disabled, danger }: {
  icon: React.ComponentType<{ size?: number }>; label: string; hint?: string;
  onClick: (e: React.MouseEvent) => void; disabled?: boolean; danger?: boolean;
}) {
  return (
    <button disabled={disabled} onClick={onClick}
      className={`w-full flex items-center gap-2 px-3 py-1.5 text-left hover:bg-base-200 disabled:opacity-30 disabled:hover:bg-transparent ${danger ? 'text-error' : 'text-base-content/80'}`}>
      <Icon size={13} />
      <span className="flex-1">{label}</span>
      {hint && <span className="text-[10px] text-base-content/30">{hint}</span>}
    </button>
  );
}

function Divider() {
  return <div className="my-1 border-t border-base-300/60" />;
}
