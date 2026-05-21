import { Bold, Italic, Underline, List, ListOrdered, Quote, Heading1, Heading2, Heading3, AlignLeft, AlignCenter, AlignRight, AlignJustify, Strikethrough, Subscript, Superscript, RemoveFormatting, Pencil } from 'lucide-react';

interface RichTextToolbarProps {
  isEditing: boolean;
  onStartEditing: () => void;
  elementId: string;
}

function exec(command: string, value?: string) {
  document.execCommand(command, false, value);
}

function ToolBtn({ icon: Icon, label, command, value, active }: {
  icon: React.ComponentType<{ size?: number; className?: string }>;
  label: string;
  command: string;
  value?: string;
  active?: boolean;
}) {
  return (
    <button
      type="button"
      title={label}
      onPointerDown={(e) => { e.preventDefault(); exec(command, value); }}
      className={`p-1 rounded transition-colors ${active ? 'bg-primary/20 text-primary' : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30'}`}
    >
      <Icon size={14} />
    </button>
  );
}

export default function RichTextToolbar({ isEditing, onStartEditing, elementId: _elementId }: RichTextToolbarProps) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Content</h3>
        {!isEditing && (
          <button
            type="button"
            onClick={onStartEditing}
            className="btn btn-xs btn-primary gap-1"
          >
            <Pencil size={10} /> Edit text
          </button>
        )}
      </div>

      {isEditing && (
        <>
          {/* Headings */}
          <div className="flex gap-0.5">
            <button type="button" title="Heading 1" onPointerDown={e => { e.preventDefault(); exec('formatBlock', 'h1'); }}
              className="p-1 rounded text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30"><Heading1 size={14} /></button>
            <button type="button" title="Heading 2" onPointerDown={e => { e.preventDefault(); exec('formatBlock', 'h2'); }}
              className="p-1 rounded text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30"><Heading2 size={14} /></button>
            <button type="button" title="Heading 3" onPointerDown={e => { e.preventDefault(); exec('formatBlock', 'h3'); }}
              className="p-1 rounded text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30"><Heading3 size={14} /></button>
            <button type="button" title="Paragraph" onPointerDown={e => { e.preventDefault(); exec('formatBlock', 'p'); }}
              className="p-1 rounded text-[10px] font-medium text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30">P</button>
          </div>

          {/* Inline formatting */}
          <div className="flex gap-0.5 flex-wrap">
            <ToolBtn icon={Bold} label="Bold (Ctrl+B)" command="bold" />
            <ToolBtn icon={Italic} label="Italic (Ctrl+I)" command="italic" />
            <ToolBtn icon={Underline} label="Underline (Ctrl+U)" command="underline" />
            <ToolBtn icon={Strikethrough} label="Strikethrough" command="strikeThrough" />
            <ToolBtn icon={Subscript} label="Subscript" command="subscript" />
            <ToolBtn icon={Superscript} label="Superscript" command="superscript" />
          </div>

          {/* Lists & quote */}
          <div className="flex gap-0.5">
            <ToolBtn icon={List} label="Bullet list" command="insertUnorderedList" />
            <ToolBtn icon={ListOrdered} label="Numbered list" command="insertOrderedList" />
            <ToolBtn icon={Quote} label="Block quote" command="formatBlock" value="blockquote" />
          </div>

          {/* Alignment */}
          <div className="flex gap-0.5">
            <ToolBtn icon={AlignLeft} label="Align left" command="justifyLeft" />
            <ToolBtn icon={AlignCenter} label="Align center" command="justifyCenter" />
            <ToolBtn icon={AlignRight} label="Align right" command="justifyRight" />
            <ToolBtn icon={AlignJustify} label="Justify" command="justifyFull" />
          </div>

          {/* Font size shortcuts */}
          <div className="flex gap-1 items-center">
            <label className="text-[9px] text-base-content/30">Size:</label>
            {[1, 2, 3, 4, 5, 6, 7].map(size => (
              <button key={size} type="button" title={`Font size ${size}`}
                onPointerDown={e => { e.preventDefault(); exec('fontSize', String(size)); }}
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
                onPointerDown={e => { e.preventDefault(); exec('foreColor', color); }}
                className="w-4 h-4 rounded border border-base-300/30 cursor-pointer"
                style={{ backgroundColor: color }}
              />
            ))}
          </div>

          {/* Clear formatting */}
          <button type="button" title="Clear formatting"
            onPointerDown={e => { e.preventDefault(); exec('removeFormat'); }}
            className="flex items-center gap-1 text-[9px] text-base-content/40 hover:text-base-content/60">
            <RemoveFormatting size={12} /> Clear formatting
          </button>

          <p className="text-[8px] text-base-content/25 italic">
            Click on the text frame to edit. Use the controls above to format selected text.
          </p>
        </>
      )}
    </div>
  );
}
