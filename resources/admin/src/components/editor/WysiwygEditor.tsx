import { useEffect, useRef, useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import { promoteWordHeadings } from '@/lib/clipboardNormalizer';
import { RichImage, type RichImageAttrs } from './extensions/RichImage';
import { ImageSettingsDialog } from './ImageSettingsDialog';
import { AssetPicker } from '@/components/ui/AssetPicker';
import {
  Bold, Italic, Underline as UnderlineIcon, Strikethrough, Link as LinkIcon, List, ListOrdered,
  AlignLeft, AlignCenter, AlignRight, Heading1, Heading2, Heading3, Quote, Code, Undo, Redo, Minus,
  Unlink, ImageIcon,
} from 'lucide-react';

/**
 * Smart plain-text to HTML converter.
 * Detects headings, lists, quotes, and paragraphs from plain text structure.
 */
function plainTextToHtml(text: string): string {
  const lines = text.split('\n');
  const result: string[] = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i].trimEnd();

    // Skip empty lines
    if (!line.trim()) { i++; continue; }

    // Detect ordered list (lines starting with number + dot/comma/parenthesis)
    if (/^\d+[\.\),]\s/.test(line)) {
      const items: string[] = [];
      while (i < lines.length && /^\d+[\.\),]\s/.test(lines[i]?.trimEnd() || '')) {
        items.push(lines[i].trimEnd().replace(/^\d+[\.\),]\s+/, ''));
        i++;
      }
      result.push('<ol>' + items.map(item => `<li>${esc(item)}</li>`).join('') + '</ol>');
      continue;
    }

    // Detect unordered list (lines starting with - or • or *)
    if (/^[\-\•\*]\s/.test(line)) {
      const items: string[] = [];
      while (i < lines.length && /^[\-\•\*]\s/.test(lines[i]?.trimEnd() || '')) {
        items.push(lines[i].trimEnd().replace(/^[\-\•\*]\s+/, ''));
        i++;
      }
      result.push('<ul>' + items.map(item => `<li>${esc(item)}</li>`).join('') + '</ul>');
      continue;
    }

    // Detect blockquote (lines starting with > or wrapped in „..." or "...")
    if (/^>\s/.test(line) || /^[„"]/.test(line.trim())) {
      const quoteLines: string[] = [];
      while (i < lines.length) {
        const l = lines[i]?.trimEnd() || '';
        if (/^>\s/.test(l)) { quoteLines.push(l.replace(/^>\s+/, '')); i++; }
        else if (/^[„"]/.test(l.trim()) && quoteLines.length === 0) { quoteLines.push(l); i++; }
        else break;
      }
      result.push('<blockquote><p>' + quoteLines.map(esc).join('<br>') + '</p></blockquote>');
      continue;
    }

    // No heading guessing: plain text pastes as paragraphs. The old heuristic
    // (short line + no trailing period + blank line after) promoted ordinary
    // label/price lines like "Цена: 0000лв" into <h2>/<h3>. Mark real headings
    // with the H1/H2/H3 toolbar buttons instead.

    // Regular paragraph — collect consecutive non-empty lines
    const paraLines: string[] = [];
    while (i < lines.length && lines[i]?.trim()) {
      paraLines.push(lines[i].trimEnd());
      i++;
    }
    result.push('<p>' + paraLines.map(esc).join('<br>') + '</p>');
  }

  return result.join('');
}

function esc(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

interface WysiwygEditorProps {
  content: string;
  onChange: (html: string) => void;
  placeholder?: string;
  minHeight?: number;
  className?: string;
}

const EMPTY_IMAGE: RichImageAttrs = { src: '', alt: '', caption: '', href: null, target: null, align: 'center', width: null };

export default function WysiwygEditor({ content, onChange, placeholder = 'Start typing...', minHeight = 200, className = '' }: WysiwygEditorProps) {
  // Image insertion flow: media library → settings sheet → node. Editing an
  // existing image (toolbar button while selected, or double-click) reopens
  // the same settings sheet pre-filled.
  const [pickerOpen, setPickerOpen] = useState(false);
  const [imageDialog, setImageDialog] = useState<{ open: boolean; isNew: boolean; attrs: RichImageAttrs }>({ open: false, isNew: true, attrs: EMPTY_IMAGE });
  const openImageDialog = (attrs: RichImageAttrs, isNew: boolean) => setImageDialog({ open: true, isNew, attrs });

  // Toolbar sticks to the top of the canvas scroll container while the block
  // is in view. The canvas has its own sticky header (mode/device toggles), so
  // offset by its height; outside the canvas (post editor etc.) it's just 0.
  const rootRef = useRef<HTMLDivElement>(null);
  const [stickyTop, setStickyTop] = useState(0);
  useEffect(() => {
    const root = rootRef.current;
    if (!root) return;
    const scroll = root.closest<HTMLElement>('[data-canvas-scroll]');
    const header = scroll?.querySelector<HTMLElement>(':scope > [data-canvas-toolbar]');
    if (!header) { setStickyTop(0); return; }
    const measure = () => setStickyTop(header.offsetHeight);
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(header);
    return () => ro.disconnect();
  }, []);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: { levels: [1, 2, 3, 4] },
      }),
      Link.configure({ openOnClick: false, HTMLAttributes: { class: 'text-blue-600 underline' } }),
      RichImage,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      Underline,
      Placeholder.configure({ placeholder }),
    ],
    content,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
    editorProps: {
      handleDoubleClickOn(_view, _pos, node) {
        if (node.type.name === 'richImage') {
          openImageDialog({ ...EMPTY_IMAGE, ...(node.attrs as RichImageAttrs) }, false);
          return true;
        }
        return false;
      },
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none editor-canvas-light',
        style: `min-height: ${minHeight}px; padding: 12px;`,
      },
      // Keep headings, bold, italic, lists, links, text colors.
      // First promote Word/Docs "visual headings" (bold/enlarged paragraphs)
      // to real <hN> — Word only emits <h1-6> for its built-in Heading styles,
      // so hand-formatted titles would otherwise paste as body text.
      // Then strip font-family, background-color, and class attributes.
      transformPastedHTML(html: string) {
        return promoteWordHeadings(html)
          .replace(/font-family\s*:\s*[^;"]+(;|")/gi, '$1')
          .replace(/background-color\s*:\s*[^;"]+(;|")/gi, '$1')
          .replace(/background\s*:\s*(?!none)[^;"]+(;|")/gi, '$1')
          .replace(/\sclass="[^"]*"/gi, '');
      },
    },
  });

  // Update content from outside if it changes
  useEffect(() => {
    if (editor && content !== editor.getHTML()) {
      editor.commands.setContent(content, { emitUpdate: false });
    }
  }, [content]);

  // Smart plain-text paste handler — converts structured text to HTML
  useEffect(() => {
    if (!editor) return;
    const el = editor.view.dom;
    const handler = (e: ClipboardEvent) => {
      const html = e.clipboardData?.getData('text/html');
      if (html && html.trim().length > 30) return; // real HTML — let TipTap handle
      const text = e.clipboardData?.getData('text/plain');
      if (text && text.includes('\n')) {
        e.preventDefault();
        e.stopPropagation();
        editor.commands.insertContent(plainTextToHtml(text));
      }
    };
    el.addEventListener('paste', handler, { capture: true });
    return () => el.removeEventListener('paste', handler, { capture: true } as any);
  }, [editor]);

  if (!editor) return null;

  return (
    <div ref={rootRef} className={`border border-gray-200 rounded-lg bg-white ${className}`}>
      {/* Toolbar — sticky within the scroll container (no overflow-hidden on the wrapper, or sticky breaks) */}
      <div className="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-gray-200 bg-gray-50 rounded-t-lg sticky z-10" style={{ top: stickyTop }}>
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
        <ToolBtn active={editor.isActive('richImage')} onClick={() => {
          if (editor.isActive('richImage')) {
            openImageDialog({ ...EMPTY_IMAGE, ...(editor.getAttributes('richImage') as RichImageAttrs) }, false);
          } else {
            setPickerOpen(true);
          }
        }} icon={ImageIcon} title={editor.isActive('richImage') ? 'Image settings' : 'Insert Image'} />

        <div className="flex-1" />

        <ToolBtn onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()} icon={Undo} title="Undo" />
        <ToolBtn onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()} icon={Redo} title="Redo" />
      </div>

      {/* Editor content */}
      <EditorContent editor={editor} />

      {/* Insert image: media library first, then the settings sheet */}
      <AssetPicker
        open={pickerOpen}
        onClose={() => setPickerOpen(false)}
        onSelect={(asset) => {
          setPickerOpen(false);
          openImageDialog({ ...EMPTY_IMAGE, src: asset.url, alt: guessAlt(asset.filename) }, true);
        }}
        accept="image"
      />
      <ImageSettingsDialog
        open={imageDialog.open}
        isNew={imageDialog.isNew}
        initial={imageDialog.attrs}
        onClose={() => setImageDialog(d => ({ ...d, open: false }))}
        onSave={(attrs) => {
          setImageDialog(d => ({ ...d, open: false }));
          if (imageDialog.isNew) {
            editor.chain().focus().insertRichImage(attrs).run();
          } else {
            editor.chain().focus().updateRichImage(attrs).run();
          }
        }}
      />
    </div>
  );
}

/** "my-photo_final.jpg" → "my photo final" as a starting alt text the editor can refine. */
function guessAlt(filename: string): string {
  return (filename || '')
    .replace(/\.[a-z0-9]+$/i, '')
    .replace(/[-_]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function ToolBtn({ active, onClick, icon: Icon, title, disabled, small }: {
  active?: boolean; onClick: () => void; icon: any; title: string; disabled?: boolean; small?: boolean;
}) {
  return (
    <button onClick={onClick} disabled={disabled} title={title}
      className={`${small ? 'p-1' : 'p-1.5'} rounded transition-colors ${
        active ? 'bg-blue-100 text-blue-600' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-200'
      } disabled:opacity-30 disabled:cursor-default`}>
      <Icon size={small ? 13 : 14} />
    </button>
  );
}

function Divider() {
  return <div className="w-px h-5 bg-gray-300 mx-0.5" />;
}
