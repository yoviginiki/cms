import { useEffect } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import {
  Bold, Italic, Underline as UnderlineIcon, Strikethrough, Link as LinkIcon, List, ListOrdered,
  AlignLeft, AlignCenter, AlignRight, Heading1, Heading2, Heading3, Quote, Code, Undo, Redo, Minus,
  Unlink, ImageIcon,
} from 'lucide-react';

interface WysiwygEditorProps {
  content: string;
  onChange: (html: string) => void;
  placeholder?: string;
  minHeight?: number;
  className?: string;
}

export default function WysiwygEditor({ content, onChange, placeholder = 'Start typing...', minHeight = 200, className = '' }: WysiwygEditorProps) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: { levels: [1, 2, 3, 4] },
      }),
      Link.configure({ openOnClick: false, HTMLAttributes: { class: 'text-blue-600 underline' } }),
      Image.configure({ inline: false, allowBase64: false }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      Underline,
      Placeholder.configure({ placeholder }),
    ],
    content,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none editor-canvas-light',
        style: `min-height: ${minHeight}px; padding: 12px;`,
      },
    },
  });

  // Update content from outside if it changes
  useEffect(() => {
    if (editor && content !== editor.getHTML()) {
      editor.commands.setContent(content, { emitUpdate: false });
    }
  }, [content]);

  if (!editor) return null;

  return (
    <div className={`border border-base-300/40 rounded-lg overflow-hidden bg-base-100 ${className}`}>
      {/* Toolbar */}
      <div className="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-base-300/30 bg-base-200/30">
        <ToolBtn active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()} icon={Bold} title="Bold" />
        <ToolBtn active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()} icon={Italic} title="Italic" />
        <ToolBtn active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()} icon={UnderlineIcon} title="Underline" />
        <ToolBtn active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()} icon={Strikethrough} title="Strikethrough" />

        <Divider />

        <ToolBtn active={editor.isActive('heading', { level: 1 })} onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()} icon={Heading1} title="Heading 1" />
        <ToolBtn active={editor.isActive('heading', { level: 2 })} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()} icon={Heading2} title="Heading 2" />
        <ToolBtn active={editor.isActive('heading', { level: 3 })} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()} icon={Heading3} title="Heading 3" />

        <Divider />

        <ToolBtn active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()} icon={List} title="Bullet List" />
        <ToolBtn active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()} icon={ListOrdered} title="Numbered List" />
        <ToolBtn active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()} icon={Quote} title="Blockquote" />
        <ToolBtn active={editor.isActive('codeBlock')} onClick={() => editor.chain().focus().toggleCodeBlock().run()} icon={Code} title="Code Block" />

        <Divider />

        <ToolBtn active={editor.isActive({ textAlign: 'left' })} onClick={() => editor.chain().focus().setTextAlign('left').run()} icon={AlignLeft} title="Align Left" />
        <ToolBtn active={editor.isActive({ textAlign: 'center' })} onClick={() => editor.chain().focus().setTextAlign('center').run()} icon={AlignCenter} title="Align Center" />
        <ToolBtn active={editor.isActive({ textAlign: 'right' })} onClick={() => editor.chain().focus().setTextAlign('right').run()} icon={AlignRight} title="Align Right" />

        <Divider />

        <ToolBtn active={editor.isActive('link')} onClick={() => {
          if (editor.isActive('link')) {
            editor.chain().focus().unsetLink().run();
          } else {
            const url = prompt('Enter URL:');
            if (url) editor.chain().focus().setLink({ href: url }).run();
          }
        }} icon={editor.isActive('link') ? Unlink : LinkIcon} title={editor.isActive('link') ? 'Remove Link' : 'Add Link'} />

        <ToolBtn onClick={() => editor.chain().focus().setHorizontalRule().run()} icon={Minus} title="Horizontal Rule" />
        <ToolBtn onClick={() => {
          const url = prompt('Image URL:');
          if (url) editor.chain().focus().setImage({ src: url }).run();
        }} icon={ImageIcon} title="Insert Image" />

        <div className="flex-1" />

        <ToolBtn onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()} icon={Undo} title="Undo" />
        <ToolBtn onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()} icon={Redo} title="Redo" />
      </div>

      {/* Editor content */}
      <EditorContent editor={editor} />
    </div>
  );
}

function ToolBtn({ active, onClick, icon: Icon, title, disabled, small }: {
  active?: boolean; onClick: () => void; icon: any; title: string; disabled?: boolean; small?: boolean;
}) {
  return (
    <button onClick={onClick} disabled={disabled} title={title}
      className={`${small ? 'p-1' : 'p-1.5'} rounded transition-colors ${
        active ? 'bg-primary/10 text-primary' : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-200/50'
      } disabled:opacity-30 disabled:cursor-default`}>
      <Icon size={small ? 13 : 14} />
    </button>
  );
}

function Divider() {
  return <div className="w-px h-5 bg-base-300/30 mx-0.5" />;
}
