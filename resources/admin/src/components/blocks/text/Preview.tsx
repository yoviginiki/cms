import { useEffect, useRef } from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { resolveTextShadow } from '@/lib/blockStyles';

const safeColor = (v: string) => /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/.test(v.trim()) ? v.trim() : '';
const safeDim = (v: string) => /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

export const TextPreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const content = (data.content as string) || '';

  // Typography controls — applied to inner content
  const textAlign = (data.textAlign as string) || '';
  const textColor = safeColor((data.textColor as string) || '');
  const fontSize = safeDim((data.fontSize as string) || '');
  const fontWeight = (data.fontWeight as string) || '';
  const fontStyle = (data.fontStyle as string) || '';
  const lineHeight = (data.lineHeight as string) || '';
  const letterSpacing = (data.letterSpacing as string) || '';
  const textShadow = resolveTextShadow(data.textShadow);

  const style: React.CSSProperties = {
    ...(textAlign ? { textAlign: textAlign as React.CSSProperties['textAlign'] } : {}),
    ...(textColor ? { color: textColor } : {}),
    ...(fontSize ? { fontSize } : {}),
    ...(fontWeight ? { fontWeight } : {}),
    ...(fontStyle === 'italic' ? { fontStyle: 'italic' } : {}),
    ...(lineHeight ? { lineHeight } : {}),
    ...(letterSpacing ? { letterSpacing } : {}),
    ...(textShadow ? { textShadow } : {}),
  };

  /* Seamless inline editing: NO box, NO toolbar — you just type in place with
     the block's own styling. Formatting (bold/italic/lists…) lives in the
     inspector's rich-text editor, which live-syncs here. Uncontrolled while
     focused so the caret never resets; re-syncs from data when blurred. */
  const ref = useRef<HTMLDivElement>(null);
  const focusedRef = useRef(false);

  useEffect(() => {
    const el = ref.current;
    if (el && !focusedRef.current && el.innerHTML !== content) {
      el.innerHTML = content || '';
    }
  }, [content, isSelected]);

  if (isSelected) {
    return (
      <div
        ref={ref}
        className="prose max-w-none outline-none min-w-[60px] min-h-[1em] cursor-text empty:before:content-['Type_here…'] empty:before:text-gray-400"
        style={style}
        contentEditable
        suppressContentEditableWarning
        onFocus={() => { focusedRef.current = true; }}
        onBlur={() => { focusedRef.current = false; }}
        onInput={e => onUpdate({ content: (e.target as HTMLElement).innerHTML })}
        onPointerDown={e => e.stopPropagation()}
        dangerouslySetInnerHTML={{ __html: content }}
      />
    );
  }

  if (!content) {
    return (
      <div className="prose max-w-none" style={style}>
        <p className="text-gray-400 italic">Click to add text...</p>
      </div>
    );
  }

  return <div className="prose max-w-none" style={style} dangerouslySetInnerHTML={{ __html: content }} />;
};
