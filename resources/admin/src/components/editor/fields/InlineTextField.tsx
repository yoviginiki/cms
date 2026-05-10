import { useRef, useCallback, useEffect, useState, useId } from 'react';

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
  /** Show character count indicator. Default: false. */
  showCharacterCount?: boolean;
  /** Recommended character length — shows warning when exceeded. */
  recommendedLength?: number;
  /** Hard max character length — prevents input beyond this when warnOnly=false. */
  maxLength?: number;
  /** If true, exceeding limits shows warning only without blocking. Default: true. */
  warnOnly?: boolean;
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
 * - Character count is editor-only UI — not saved to block data
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
  showCharacterCount = false,
  recommendedLength,
  maxLength,
  warnOnly = true,
}: InlineTextFieldProps) {
  const ref = useRef<HTMLElement>(null);
  const [isEditing, setIsEditing] = useState(false);
  const [liveLength, setLiveLength] = useState(value.length);
  const uniqueId = useId();

  // Set initial textContent and sync when value changes externally
  useEffect(() => {
    if (ref.current && !isEditing) {
      const current = ref.current.textContent ?? '';
      if (current !== value) {
        ref.current.textContent = value || '';
      }
    }
    setLiveLength(value.length);
  }, [value, isEditing]);

  const commit = useCallback(() => {
    if (!ref.current) return;
    let text = (ref.current.textContent ?? '').trim();
    // Enforce hard max on commit if not warn-only
    if (maxLength && !warnOnly && text.length > maxLength) {
      text = text.slice(0, maxLength);
      ref.current.textContent = text;
    }
    if (text !== value) {
      onChange(text);
    }
    setIsEditing(false);
  }, [value, onChange, maxLength, warnOnly]);

  const handleFocus = useCallback(() => {
    setIsEditing(true);
  }, []);

  const handleBlur = useCallback(() => {
    commit();
  }, [commit]);

  // Track live character count during editing
  const handleInput = useCallback(() => {
    if (ref.current) {
      setLiveLength((ref.current.textContent ?? '').length);
    }
  }, []);

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
    // Update live count after paste
    if (ref.current) {
      setLiveLength((ref.current.textContent ?? '').length);
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

  // Character count display logic
  const displayCount = isEditing ? liveLength : value.length;
  const limit = recommendedLength || maxLength;
  const isOverRecommended = recommendedLength ? displayCount > recommendedLength : false;
  const isOverMax = maxLength ? displayCount > maxLength : false;
  const showCounter = showCharacterCount && (isEditing || displayCount > 0);
  const counterId = showCounter ? `char-count-${uniqueId}` : undefined;

  // Shared props for the editable element
  const editableProps = {
    ref: ref as React.RefObject<never>,
    contentEditable: true,
    suppressContentEditableWarning: true,
    role: 'textbox' as const,
    'aria-placeholder': placeholder,
    'aria-label': placeholder,
    'aria-multiline': multiline || undefined,
    'aria-describedby': counterId,
    tabIndex: 0,
    className: `inline-editable outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-1 rounded-sm cursor-text ${className}`,
    style: {
      ...style,
      minWidth: '2rem',
      display: Tag === 'span' ? ('inline-block' as const) : undefined,
    },
    onFocus: handleFocus,
    onBlur: handleBlur,
    onInput: handleInput,
    onKeyDown: handleKeyDown,
    onPaste: handlePaste,
    onMouseDown: handleMouseDown,
    onDragStart: (e: React.DragEvent) => { if (preventDrag) e.preventDefault(); },
    'data-placeholder': showPlaceholder ? placeholder : undefined,
  };

  // When counter is not shown, render the editable element directly
  // (no wrapper) to preserve original DOM structure and valid HTML.
  if (!showCounter) {
    return <Tag {...editableProps} />;
  }

  // When counter is shown, wrap in a positioned container for the counter overlay.
  return (
    <span
      className="inline-editable-wrapper"
      style={{ position: 'relative', display: Tag === 'span' ? 'inline-block' : 'block' }}
    >
      <Tag {...editableProps} />
      <span
        id={counterId}
        className={`inline-char-count pointer-events-none select-none ${
          isOverMax ? 'text-error' : isOverRecommended ? 'text-warning' : 'text-base-content/30'
        }`}
        style={{
          position: 'absolute',
          right: 0,
          bottom: '-1.1rem',
          fontSize: '9px',
          lineHeight: 1,
          whiteSpace: 'nowrap',
        }}
        aria-live="polite"
        aria-atomic
      >
        {displayCount}{limit ? ` / ${limit}` : ''}
      </span>
    </span>
  );
}
