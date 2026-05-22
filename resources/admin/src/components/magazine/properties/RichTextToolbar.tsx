// Rich text toolbar for DTP text frames
import { Bold, Italic, Underline, List, ListOrdered, Quote, Heading1, Heading2, Heading3, AlignLeft, AlignCenter, AlignRight, AlignJustify, Strikethrough, RemoveFormatting, Pencil, ImageIcon } from 'lucide-react';

interface RichTextToolbarProps {
  isEditing: boolean;
  onStartEditing: () => void;
  elementId: string;
  onFormatText?: (command: string, value?: string) => void;
  onInsertImage?: () => void;
}

export default function RichTextToolbar({ isEditing, onStartEditing, elementId: _elementId, onFormatText, onInsertImage }: RichTextToolbarProps) {
  const fmt = (command: string, value?: string) => {
    if (onFormatText) {
      onFormatText(command, value);
    }
  };

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
            <ToolBtn icon={Heading1} label="Heading 1" onExec={() => fmt('formatBlock', 'h1')} />
            <ToolBtn icon={Heading2} label="Heading 2" onExec={() => fmt('formatBlock', 'h2')} />
            <ToolBtn icon={Heading3} label="Heading 3" onExec={() => fmt('formatBlock', 'h3')} />
            <ToolBtn label="P" title="Paragraph" onExec={() => fmt('formatBlock', 'p')} />
          </div>

          {/* Inline formatting */}
          <div className="flex gap-0.5 flex-wrap">
            <ToolBtn icon={Bold} label="Bold" onExec={() => fmt('bold')} />
            <ToolBtn icon={Italic} label="Italic" onExec={() => fmt('italic')} />
            <ToolBtn icon={Underline} label="Underline" onExec={() => fmt('underline')} />
            <ToolBtn icon={Strikethrough} label="Strikethrough" onExec={() => fmt('strikeThrough')} />
          </div>

          {/* Lists & quote */}
          <div className="flex gap-0.5">
            <ToolBtn icon={List} label="Bullet list" onExec={() => fmt('insertUnorderedList')} />
            <ToolBtn icon={ListOrdered} label="Numbered list" onExec={() => fmt('insertOrderedList')} />
            <ToolBtn icon={Quote} label="Block quote" onExec={() => fmt('formatBlock', 'blockquote')} />
          </div>

          {/* Alignment */}
          <div className="flex gap-0.5">
            <ToolBtn icon={AlignLeft} label="Align left" onExec={() => fmt('justifyLeft')} />
            <ToolBtn icon={AlignCenter} label="Align center" onExec={() => fmt('justifyCenter')} />
            <ToolBtn icon={AlignRight} label="Align right" onExec={() => fmt('justifyRight')} />
            <ToolBtn icon={AlignJustify} label="Justify" onExec={() => fmt('justifyFull')} />
          </div>

          {/* Insert image into text */}
          {onInsertImage && (
            <button type="button"
              onMouseDown={e => e.preventDefault()}
              onClick={onInsertImage}
              className="flex items-center gap-1 px-2 py-1 rounded text-[10px] font-medium bg-base-300/30 text-base-content/60 hover:text-base-content/80 hover:bg-base-300/50 w-full">
              <ImageIcon size={12} /> Insert Image
            </button>
          )}

          {/* Clear formatting */}
          <button type="button"
            onMouseDown={e => e.preventDefault()}
            onClick={() => fmt('removeFormat')}
            className="flex items-center gap-1 text-[9px] text-base-content/40 hover:text-base-content/60">
            <RemoveFormatting size={12} /> Clear formatting
          </button>

          <p className="text-[8px] text-base-content/25 italic">
            Select text in the frame, then click a formatting button.
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
