import React, { useState } from 'react';
import type { MagStyleDefinition } from '@/types/magazine';

interface StylesPanelProps {
  styles: MagStyleDefinition[];
  selectedElementId: string | null;
  onApplyStyle: (styleId: string) => void;
  onCreateStyle: (type: 'paragraph' | 'character') => void;
  onDeleteStyle: (id: string) => void;
}

interface StyleRowProps {
  style: MagStyleDefinition;
  onApply: () => void;
  onDelete: () => void;
  hasSelection: boolean;
}

function StyleRow({ style, onApply, onDelete, hasSelection }: StyleRowProps) {
  const [confirmDelete, setConfirmDelete] = useState(false);

  const previewStyle: React.CSSProperties = {
    fontFamily: style.properties.fontFamily || 'Inter',
    fontSize: Math.min(style.properties.fontSize || 14, 16),
    fontWeight: style.properties.fontWeight || 400,
    fontStyle: style.properties.fontStyle || 'normal',
    lineHeight: 1.4,
    color: style.properties.textColor || undefined,
    textTransform: (style.properties.textTransform as React.CSSProperties['textTransform']) || undefined,
    letterSpacing: style.properties.letterSpacing ? `${style.properties.letterSpacing}px` : undefined,
  };

  return (
    <div className="group flex items-center gap-2 px-3 py-1.5 hover:bg-base-content/5 transition-colors">
      <button
        className="flex-1 text-left min-w-0"
        onClick={onApply}
        disabled={!hasSelection}
        title={hasSelection ? `Apply "${style.name}"` : 'Select an element to apply styles'}
      >
        <div className="flex items-center gap-2">
          <span className="text-sm text-base-content/80 truncate">{style.name}</span>
          {style.isDefault && (
            <span className="badge badge-xs badge-ghost text-base-content/40">Default</span>
          )}
        </div>
        <p className="truncate text-base-content/50 mt-0.5" style={previewStyle}>
          The quick brown fox
        </p>
      </button>

      {/* Delete */}
      {!style.isDefault && (
        <>
          {confirmDelete ? (
            <div className="flex items-center gap-1 flex-shrink-0">
              <button
                className="btn btn-error btn-xs"
                onClick={() => { onDelete(); setConfirmDelete(false); }}
              >
                Delete
              </button>
              <button
                className="btn btn-ghost btn-xs"
                onClick={() => setConfirmDelete(false)}
              >
                Cancel
              </button>
            </div>
          ) : (
            <button
              className="btn btn-ghost btn-xs btn-square opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0"
              onClick={() => setConfirmDelete(true)}
              title="Delete style"
            >
              <svg className="w-3.5 h-3.5 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          )}
        </>
      )}
    </div>
  );
}

export default function StylesPanel({
  styles,
  selectedElementId,
  onApplyStyle,
  onCreateStyle,
  onDeleteStyle,
}: StylesPanelProps) {
  const paragraphStyles = styles.filter((s) => s.type === 'paragraph');
  const characterStyles = styles.filter((s) => s.type === 'character');
  const hasSelection = selectedElementId !== null;

  return (
    <div className="flex flex-col h-full bg-base-200/50">
      <div className="px-3 py-2 border-b border-base-content/10">
        <h3 className="text-xs font-semibold uppercase tracking-wider text-base-content/50">
          Styles
        </h3>
      </div>

      <div className="flex-1 overflow-y-auto">
        {/* Paragraph styles */}
        <div className="py-1">
          <div className="flex items-center justify-between px-3 py-1.5">
            <span className="text-xs font-semibold text-base-content/60">Paragraph styles</span>
            <button
              className="btn btn-ghost btn-xs btn-square"
              onClick={() => onCreateStyle('paragraph')}
              title="New paragraph style"
            >
              <svg className="w-3.5 h-3.5 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
            </button>
          </div>

          {paragraphStyles.length === 0 && (
            <p className="text-xs text-base-content/30 px-3 py-2">No paragraph styles</p>
          )}

          {paragraphStyles.map((style) => (
            <StyleRow
              key={style.id}
              style={style}
              onApply={() => onApplyStyle(style.id)}
              onDelete={() => onDeleteStyle(style.id)}
              hasSelection={hasSelection}
            />
          ))}
        </div>

        {/* Divider */}
        <div className="border-t border-base-content/10 mx-3" />

        {/* Character styles */}
        <div className="py-1">
          <div className="flex items-center justify-between px-3 py-1.5">
            <span className="text-xs font-semibold text-base-content/60">Character styles</span>
            <button
              className="btn btn-ghost btn-xs btn-square"
              onClick={() => onCreateStyle('character')}
              title="New character style"
            >
              <svg className="w-3.5 h-3.5 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
            </button>
          </div>

          {characterStyles.length === 0 && (
            <p className="text-xs text-base-content/30 px-3 py-2">No character styles</p>
          )}

          {characterStyles.map((style) => (
            <StyleRow
              key={style.id}
              style={style}
              onApply={() => onApplyStyle(style.id)}
              onDelete={() => onDeleteStyle(style.id)}
              hasSelection={hasSelection}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
