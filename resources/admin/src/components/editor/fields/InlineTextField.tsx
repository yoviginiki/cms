import { useRef, useCallback, useEffect, useState } from 'react';

interface InlineTextFieldProps {
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
  /** HTML tag to render. Default: 'span'. */
  as?: 'span' | 'p' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'div';
  /** Additional CSS class names. */
  className?: string;
  /** Inline styles applied to the element. */
  style?: React.CSSProperties;
  /** Allow multiline input via Shift+Enter. Default: false. */
  multiline?: boolean;
  /** Prevent drag events from propagating while editing. Default: true. */
  preventDrag?: boolean;
}

/**
 * Inline plain-text editing primitive for the editor canvas.
 *
 * Uses contentEditable for in-place editing. Only textContent is read —
 * innerHTML is never used for data. Pasted HTML is stripped to plain text.
 *
 * Safety:
 * - Never reads innerHTML for data
 * - Never uses dangerouslySetInnerHTML
 * - Strips all HTML from pasted content
 * - Emits only plain text via onChange
 * - Admin-only component — not rendered in published Blade output
 */
export function InlineTextField({
  value,
  placeholder = '',
  onChange,
  as: Tag = 'span',
  className = '',
  style,
  multiline = false,
  preventDrag = true,
}: InlineTextFieldProps) {
  const ref = useRef<HTMLElement>(null);
  const [isEditing, setIsEditing] = useState(false);

  // Set initial textContent and sync when value changes externally
  useEffect(() => {
    if (ref.current && !isEditing) {
      const current = ref.current.textContent ?? '';
      if (current !== value) {
        ref.current.textContent = value || '';
      }
    }
  }, [value, isEditing]);

  const commit = useCallback(() => {
    if (!ref.current) return;
    const text = (ref.current.textContent ?? '').trim();
    if (text !== value) {
      onChange(text);
    }
    setIsEditing(false);
  }, [value, onChange]);

  const handleFocus = useCallback(() => {
    setIsEditing(true);
  }, []);

  const handleBlur = useCallback(() => {
    commit();
  }, [commit]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      // Stop propagation so editor shortcuts don't fire while typing
      e.stopPropagation();

      if (e.key === 'Escape') {
        // Cancel: restore original value and blur
        if (ref.current) ref.current.textContent = value;
        setIsEditing(false);
        ref.current?.blur();
        return;
      }
      if (e.key === 'Enter') {
        if (!multiline || !e.shiftKey) {
          e.preventDefault();
          commit();
          ref.current?.blur();
        }
      }
    },
    [value, multiline, commit],
  );

  // Strip HTML from paste — only allow plain text
  const handlePaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault();
    const text = e.clipboardData.getData('text/plain');
    const selection = window.getSelection();
    if (selection?.rangeCount) {
      const range = selection.getRangeAt(0);
      range.deleteContents();
      range.insertNode(document.createTextNode(text));
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
  }, []);

  // Prevent drag events while editing to avoid block drag
  const handleMouseDown = useCallback(
    (e: React.MouseEvent) => {
      if (preventDrag) {
        e.stopPropagation();
      }
    },
    [preventDrag],
  );

  const showPlaceholder = !value && !isEditing;

  return (
    <Tag
      ref={ref as React.RefObject<never>}
      contentEditable
      suppressContentEditableWarning
      role="textbox"
      aria-placeholder={placeholder}
      aria-label={placeholder}
      aria-multiline={multiline || undefined}
      tabIndex={0}
      className={`inline-editable outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-1 rounded-sm cursor-text ${className}`}
      style={{
        ...style,
        minWidth: '2rem',
        display: Tag === 'span' ? 'inline-block' : undefined,
      }}
      onFocus={handleFocus}
      onBlur={handleBlur}
      onKeyDown={handleKeyDown}
      onPaste={handlePaste}
      onMouseDown={handleMouseDown}
      onDragStart={(e: React.DragEvent) => {
        if (preventDrag) e.preventDefault();
      }}
      data-placeholder={showPlaceholder ? placeholder : undefined}
    />
  );
}
