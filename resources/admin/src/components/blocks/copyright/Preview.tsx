import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { useSiteBranding } from '../site-identity/useSiteBranding';

export function copyrightText(text: string, site: string, startYear?: number | null): string {
  const year = new Date().getFullYear();
  const start = Number(startYear) || 0;
  const years = start > 0 && start < year ? `${start}–${year}` : String(year);
  return (text?.trim() || '© {year} {site}. All rights reserved.')
    .split('{year}').join(years).split('{site}').join(site).split('{start}').join(start > 0 ? String(start) : '');
}

export const CopyrightPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as { text?: string; startYear?: number | null; align?: string };
  const b = useSiteBranding();
  return <p style={{ margin: 0, fontSize: '0.875rem', textAlign: (d.align as any) || 'left' }}>{copyrightText(d.text || '', b.name, d.startYear)}</p>;
};
