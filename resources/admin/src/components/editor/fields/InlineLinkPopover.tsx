import { useState, useRef, useEffect, useCallback } from 'react';
import { Link2, ExternalLink, X, AlertTriangle } from 'lucide-react';
import { isSafeUrl, getUrlError, isExternalUrl, normalizeUrl } from './urlHelpers';

interface InlineLinkPopoverProps {
  /** Current URL value. */
  url: string;
  /** Called when URL is committed (on Enter or Apply). */
  onChangeUrl: (url: string) => void;
  /** Placeholder text for the URL input. */
  placeholder?: string;
  /** Whether the popover trigger should be compact (icon only). */
  compact?: boolean;
  /** Additional class for the trigger wrapper. */
  className?: string;
}

/**
 * Inline link popover for editing URLs directly on the editor canvas.
 *
 * Renders a small link icon trigger. When clicked, opens a popover with:
 * - URL text input with live validation
 * - Validation error for unsafe schemes (javascript:, data:, vbscript:)
 * - Clear URL button
 * - Open link button (external URLs only)
 * - Escape to close, Enter to apply
 *
 * Reusable across blocks — not Hero-specific.
 * Does not use dangerouslySetInnerHTML or raw HTML.
 */
export function InlineLinkPopover({
  url,
  onChangeUrl,
  placeholder = 'https://...',
  compact = false,
  className = '',
}: InlineLinkPopoverProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [draft, setDraft] = useState(url);
  const [error, setError] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);
  const popoverRef = useRef<HTMLDivElement>(null);

  // Sync draft when url prop changes externally (e.g. side panel edit)
  useEffect(() => {
    if (!isOpen) {
      setDraft(url);
      setError('');
    }
  }, [url, isOpen]);

  // Focus input when popover opens
  useEffect(() => {
    if (isOpen) {
      requestAnimationFrame(() => inputRef.current?.focus());
    }
  }, [isOpen]);

  // Close on click outside
  useEffect(() => {
    if (!isOpen) return;
    const handleClickOutside = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        handleClose();
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const handleClose = useCallback(() => {
    setIsOpen(false);
    setError('');
  }, []);

  const handleApply = useCallback(() => {
    const normalized = normalizeUrl(draft);
    const urlError = getUrlError(normalized);
    if (urlError) {
      setError(urlError);
      return;
    }
    onChangeUrl(normalized);
    setIsOpen(false);
    setError('');
  }, [draft, onChangeUrl]);

  const handleClear = useCallback(() => {
    setDraft('');
    setError('');
    onChangeUrl('');
    setIsOpen(false);
  }, [onChangeUrl]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      e.stopPropagation();
      if (e.key === 'Escape') {
        handleClose();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        handleApply();
      }
    },
    [handleClose, handleApply],
  );

  const handleDraftChange = useCallback((value: string) => {
    setDraft(value);
    // Clear error on edit; re-validate on apply
    if (error) {
      const newError = getUrlError(value.trim());
      setError(newError);
    }
  }, [error]);

  const handleToggle = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    setIsOpen((prev) => !prev);
  }, []);

  const hasUrl = !!url.trim();
  const draftIsExternal = isExternalUrl(draft);
  const draftIsSafe = isSafeUrl(draft);

  return (
    <span
      ref={popoverRef}
      className={`relative inline-flex items-center ${className}`}
      onMouseDown={(e) => e.stopPropagation()}
    >
      {/* Trigger button */}
      <button
        type="button"
        onClick={handleToggle}
        className={`inline-flex items-center gap-1 transition-colors rounded ${
          hasUrl
            ? 'text-primary hover:text-primary/80'
            : 'text-base-content/30 hover:text-base-content/50'
        } ${compact ? 'p-0.5' : 'px-1.5 py-0.5 text-[10px]'}`}
        title={hasUrl ? `Link: ${url}` : 'Add link'}
        aria-label={hasUrl ? `Edit link: ${url}` : 'Add link'}
        aria-expanded={isOpen}
      >
        <Link2 size={compact ? 12 : 11} />
        {!compact && (
          <span className="truncate max-w-[120px]">
            {hasUrl ? url : 'Add link'}
          </span>
        )}
      </button>

      {/* Popover */}
      {isOpen && (
        <div
          className="absolute z-50 mt-1 top-full left-0 bg-base-100 border border-base-300/40 rounded-lg shadow-lg p-2 min-w-[280px]"
          role="dialog"
          aria-label="Edit link URL"
        >
          <div className="flex items-center gap-1.5 mb-1.5">
            <Link2 size={12} className="text-base-content/40 shrink-0" />
            <span className="text-[10px] text-base-content/40 font-medium">Link URL</span>
          </div>

          <div className="flex gap-1">
            <input
              ref={inputRef}
              type="text"
              value={draft}
              onChange={(e) => handleDraftChange(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={placeholder}
              className={`input input-bordered input-xs flex-1 text-[11px] font-mono ${
                error ? 'input-error' : ''
              }`}
              aria-invalid={!!error}
              aria-describedby={error ? 'link-popover-error' : undefined}
            />
            <button
              type="button"
              onClick={handleApply}
              disabled={!draftIsSafe}
              className="btn btn-primary btn-xs text-[10px]"
              title="Apply (Enter)"
            >
              Apply
            </button>
          </div>

          {error && (
            <div
              id="link-popover-error"
              className="flex items-start gap-1 mt-1 text-[10px] text-error"
              role="alert"
            >
              <AlertTriangle size={10} className="shrink-0 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <div className="flex items-center gap-1 mt-1.5">
            {hasUrl && (
              <button
                type="button"
                onClick={handleClear}
                className="btn btn-ghost btn-xs text-[10px] text-error gap-0.5"
                title="Remove link"
              >
                <X size={10} /> Remove
              </button>
            )}
            {draftIsExternal && draftIsSafe && draft.trim() && (
              <a
                href={draft.trim()}
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-ghost btn-xs text-[10px] gap-0.5 ml-auto"
                title="Open in new tab"
                onClick={(e) => e.stopPropagation()}
              >
                <ExternalLink size={10} /> Open
              </a>
            )}
          </div>

          <div className="text-[9px] text-base-content/30 mt-1">
            Enter to apply · Escape to cancel
          </div>
        </div>
      )}
    </span>
  );
}
