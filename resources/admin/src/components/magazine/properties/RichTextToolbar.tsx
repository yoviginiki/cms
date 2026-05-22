// Rich text toolbar for DTP text frames
import { Bold, Italic, Underline, List, ListOrdered, Quote, Heading1, Heading2, Heading3, AlignLeft, AlignCenter, AlignRight, AlignJustify, Strikethrough, RemoveFormatting, Pencil } from 'lucide-react';

interface RichTextToolbarProps {
  isEditing: boolean;
  onStartEditing: () => void;
  elementId: string;
}

// Store last selection so we can restore it when clicking toolbar buttons
let savedRange: Range | null = null;

function saveSelection() {
  const sel = window.getSelection();
  if (sel && sel.rangeCount > 0) {
    savedRange = sel.getRangeAt(0).cloneRange();
  }
}

function restoreSelection() {
  const sel = window.getSelection();
  if (sel && savedRange) {
    sel.removeAllRanges();
    sel.addRange(savedRange);
  }
}

// Listen for selection changes in contentEditable to keep savedRange updated
if (typeof window !== 'undefined') {
  document.addEventListener('selectionchange', () => {
    const active = document.activeElement;
    if (active?.getAttribute('contenteditable') === 'true') {
      saveSelection();
    }
  });
}

function execCommand(command: string, value?: string) {
  // Re-focus the contentEditable and restore selection before executing
  const editable = document.querySelector('[data-editing-id]') as HTMLElement
    ?? document.querySelector('[contenteditable="true"]') as HTMLElement;
  if (editable) {
    editable.focus();
    restoreSelection();
  }
  document.execCommand(command, false, value);
  // Re-save selection after command
  saveSelection();
}

export default function RichTextToolbar({ isEditing, onStartEditing, elementId: _elementId }: RichTextToolbarProps) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Content</h3>
        {!isEditing && (
          <button type="button" onClick={onStartEditing} className="btn btn-xs btn-primary gap-1">
            <Pencil size={10} /> Edit text
          </button>
        )}
      </div>

      {isEditing && (
        <>
          {/* Headings */}
          <div className="flex gap-0.5">
            <ToolBtn icon={Heading1} label="Heading 1" onExec={() => execCommand('formatBlock', 'h1')} />
            <ToolBtn icon={Heading2} label="Heading 2" onExec={() => execCommand('formatBlock', 'h2')} />
            <ToolBtn icon={Heading3} label="Heading 3" onExec={() => execCommand('formatBlock', 'h3')} />
            <ToolBtn label="P" title="Paragraph" onExec={() => execCommand('formatBlock', 'p')} />
          </div>

          {/* Inline formatting */}
          <div className="flex gap-0.5 flex-wrap">
            <ToolBtn icon={Bold} label="Bold (Ctrl+B)" onExec={() => execCommand('bold')} />
            <ToolBtn icon={Italic} label="Italic (Ctrl+I)" onExec={() => execCommand('italic')} />
            <ToolBtn icon={Underline} label="Underline (Ctrl+U)" onExec={() => execCommand('underline')} />
            <ToolBtn icon={Strikethrough} label="Strikethrough" onExec={() => execCommand('strikeThrough')} />
          </div>

          {/* Lists & quote */}
          <div className="flex gap-0.5">
            <ToolBtn icon={List} label="Bullet list" onExec={() => execCommand('insertUnorderedList')} />
            <ToolBtn icon={ListOrdered} label="Numbered list" onExec={() => execCommand('insertOrderedList')} />
            <ToolBtn icon={Quote} label="Block quote" onExec={() => execCommand('formatBlock', 'blockquote')} />
          </div>

          {/* Alignment */}
          <div className="flex gap-0.5">
            <ToolBtn icon={AlignLeft} label="Align left" onExec={() => execCommand('justifyLeft')} />
            <ToolBtn icon={AlignCenter} label="Align center" onExec={() => execCommand('justifyCenter')} />
            <ToolBtn icon={AlignRight} label="Align right" onExec={() => execCommand('justifyRight')} />
            <ToolBtn icon={AlignJustify} label="Justify" onExec={() => execCommand('justifyFull')} />
          </div>

          {/* Font size */}
          <div className="flex gap-1 items-center">
            <label className="text-[9px] text-base-content/30">Size:</label>
            {[1, 2, 3, 4, 5, 6, 7].map(size => (
              <button key={size} type="button" title={`Font size ${size}`}
                onMouseDown={e => e.preventDefault()}
                onClick={() => execCommand('fontSize', String(size))}
                className="w-5 h-5 flex items-center justify-center rounded text-[8px] text-base-content/40 hover:bg-base-300/30 hover:text-base-content/70">
                {size}
              </button>
            ))}
          </div>

          {/* Color */}
          <div className="flex gap-1 items-center">
            <label className="text-[9px] text-base-content/30">Color:</label>
            {['#1a1a1a', '#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#666666'].map(color => (
              <button key={color} type="button" title={color}
                onMouseDown={e => e.preventDefault()}
                onClick={() => execCommand('foreColor', color)}
                className="w-4 h-4 rounded border border-base-300/30 cursor-pointer"
                style={{ backgroundColor: color }}
              />
            ))}
          </div>

          {/* Clear formatting */}
          <button type="button" title="Clear formatting"
            onMouseDown={e => e.preventDefault()}
            onClick={() => execCommand('removeFormat')}
            className="flex items-center gap-1 text-[9px] text-base-content/40 hover:text-base-content/60">
            <RemoveFormatting size={12} /> Clear formatting
          </button>

          <p className="text-[8px] text-base-content/25 italic">
            Select text in the frame, then use controls above to format.
          </p>
        </>
      )}
    </div>
  );
}

function ToolBtn({ icon: Icon, label, onExec, title }: {
  icon?: React.ComponentType<{ size?: number; className?: string }>;
  label: string;
  onExec: () => void;
  title?: string;
}) {
  return (
    <button
      type="button"
      title={title || label}
      onMouseDown={(e) => e.preventDefault()}
      onClick={onExec}
      className="p-1 rounded transition-colors text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30"
    >
      {Icon ? <Icon size={14} /> : <span className="text-[10px] font-medium">{label}</span>}
    </button>
  );
}
