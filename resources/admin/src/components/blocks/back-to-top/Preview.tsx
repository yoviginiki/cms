import React from 'react';
import { ChevronUp } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';

export const BackToTopPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as { label?: string; style?: string; align?: string };
  const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[(block.data as any).align as string] || 'flex-start';
  const style = d.style || 'link';
  const look: React.CSSProperties = style === 'button'
    ? { padding: '0.5rem 1rem', borderRadius: 6, background: 'var(--btn-bg, var(--color-primary, #1b6df5))', color: 'var(--btn-color, #fff)' }
    : style === 'icon' ? { width: 40, height: 40, borderRadius: '50%', background: 'var(--color-bg-alt, #f1f5f9)' } : { minHeight: 36 };
  return (
    <div style={{ display: 'flex', justifyContent: justify }}>
      <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.35rem', fontSize: '0.9rem', ...look }}>
        <ChevronUp size={18} aria-hidden />
        {style !== 'icon' && <span>{d.label || 'Back to top'}</span>}
      </span>
    </div>
  );
};
