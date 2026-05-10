import { useState, useCallback } from 'react';
import { ImagePlus, Replace, X } from 'lucide-react';
import { AssetPicker } from '@/components/ui/AssetPicker';

interface InlineMediaReplaceProps {
  /** Current image/media URL. */
  value: string;
  /** Called when a new image is selected or cleared. */
  onChange: (url: string, assetId?: string) => void;
  /** Filter by media type. Default: 'image'. */
  accept?: 'image' | 'video' | 'audio' | 'all';
  /** Label for the action buttons. */
  label?: string;
  /** Show as overlay on top of content. Default: true. */
  overlay?: boolean;
  /** Additional class for the wrapper. */
  className?: string;
}

/**
 * Inline media replacement control for the editor canvas.
 *
 * Renders a subtle overlay with "Change" / "Add" / "Clear" controls
 * that trigger the AssetPicker modal. Reusable across blocks.
 *
 * Does not use dangerouslySetInnerHTML or raw HTML.
 * Does not modify Blade/published output.
 * Admin-only component.
 */
export function InlineMediaReplace({
  value,
  onChange,
  accept = 'image',
  label = 'image',
  overlay = true,
  className = '',
}: InlineMediaReplaceProps) {
  const [pickerOpen, setPickerOpen] = useState(false);

  const handleSelect = useCallback(
    (asset: { id: string; url: string; filename: string; mime_type: string }) => {
      onChange(asset.url, asset.id);
      setPickerOpen(false);
    },
    [onChange],
  );

  const handleClear = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation();
      onChange('');
    },
    [onChange],
  );

  const handleOpen = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation();
      e.preventDefault();
      setPickerOpen(true);
    },
    [],
  );

  const hasValue = !!value.trim();

  if (overlay) {
    // Overlay mode: renders controls that float over parent content
    return (
      <>
        <div
          className={`absolute inset-0 z-20 flex items-center justify-center opacity-0 hover:opacity-100 focus-within:opacity-100 transition-opacity ${className}`}
          onMouseDown={(e) => e.stopPropagation()}
        >
          <div className="flex gap-1.5 bg-base-100/90 backdrop-blur-sm rounded-lg p-1.5 shadow-lg border border-base-300/30">
            <button
              type="button"
              onClick={handleOpen}
              className="btn btn-ghost btn-xs text-[10px] gap-1"
              title={hasValue ? `Change ${label}` : `Add ${label}`}
            >
              {hasValue ? <Replace size={11} /> : <ImagePlus size={11} />}
              {hasValue ? 'Change' : 'Add'}
            </button>
            {hasValue && (
              <button
                type="button"
                onClick={handleClear}
                className="btn btn-ghost btn-xs text-[10px] text-error gap-1"
                title={`Remove ${label}`}
                aria-label={`Remove ${label}`}
              >
                <X size={11} />
              </button>
            )}
          </div>
        </div>
        <AssetPicker
          open={pickerOpen}
          onClose={() => setPickerOpen(false)}
          onSelect={handleSelect}
          accept={accept}
          currentUrl={value}
        />
      </>
    );
  }

  // Inline mode: renders as a compact button row
  return (
    <>
      <div
        className={`flex items-center gap-1 ${className}`}
        onMouseDown={(e) => e.stopPropagation()}
      >
        <button
          type="button"
          onClick={handleOpen}
          className="btn btn-ghost btn-xs text-[10px] gap-1"
          title={hasValue ? `Change ${label}` : `Add ${label}`}
        >
          {hasValue ? <Replace size={11} /> : <ImagePlus size={11} />}
          {hasValue ? `Change ${label}` : `Add ${label}`}
        </button>
        {hasValue && (
          <button
            type="button"
            onClick={handleClear}
            className="btn btn-ghost btn-xs text-[10px] text-error gap-1"
            title={`Remove ${label}`}
          >
            <X size={11} />
          </button>
        )}
      </div>
      <AssetPicker
        open={pickerOpen}
        onClose={() => setPickerOpen(false)}
        onSelect={handleSelect}
        accept={accept}
        currentUrl={value}
      />
    </>
  );
}
