import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { BlockComponentProps } from '@/types/blocks';
import { sites } from '@/lib/api';
import { langMeta, siteLanguages } from '@/lib/languages';

export const LangSwitcherPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { siteId } = useParams<{ siteId: string }>();
  const { data: site } = useQuery<any>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId!).then((r: any) => r.data.data),
    enabled: !!siteId,
  });

  const data = block.data as {
    style?: string;
    display?: string;
    flagSize?: number;
    fontSize?: number;
    gap?: number;
    uppercase?: boolean;
    separator?: string;
    alignment?: string;
    textColor?: string;
    activeColor?: string;
  };

  const languages = siteLanguages(site);
  const list = languages.length > 1 ? languages : ['en', 'bg']; // sample when not configured
  const display = data.display || 'code';
  const flagSize = data.flagSize || 18;
  const fontSize = data.fontSize || 14;
  const gap = data.gap ?? 10;
  const uppercase = data.uppercase ?? true;
  const sep = ({ slash: '/', pipe: '|', dot: '·', none: '' } as Record<string, string>)[data.separator || 'none'] ?? '';
  const justify = ({ left: 'flex-start', center: 'center', right: 'flex-end' } as Record<string, string>)[data.alignment || 'left'];
  const textColor = data.textColor || '#6b7280';
  const activeColor = data.activeColor || '#1f2937';

  const label = (lang: string, active: boolean) => {
    const meta = langMeta(lang);
    const code = uppercase ? lang.toUpperCase() : lang;
    const flag = <span style={{ fontSize: flagSize, lineHeight: 1 }}>{meta.flag}</span>;
    const textStyle: React.CSSProperties = {
      fontSize,
      color: active ? activeColor : textColor,
      fontWeight: active ? 700 : 400,
    };
    switch (display) {
      case 'name': return <span style={textStyle}>{meta.native}</span>;
      case 'flag': return flag;
      case 'flag-code': return <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35em', ...textStyle }}>{flag}<span>{code}</span></span>;
      case 'flag-name': return <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35em', ...textStyle }}>{flag}<span>{meta.native}</span></span>;
      default: return <span style={textStyle}>{code}</span>;
    }
  };

  if (data.style === 'dropdown') {
    return (
      <div style={{ display: 'flex', justifyContent: justify }}>
        <span style={{
          display: 'inline-flex', alignItems: 'center', gap: '0.4em', fontSize,
          padding: '6px 12px', borderRadius: 8, border: '1px solid #e5e7eb', background: '#fff',
        }}>
          {label(list[0], true)}
          <span style={{ fontSize: '0.7em', opacity: 0.5 }}>▾</span>
        </span>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', justifyContent: justify, alignItems: 'center', gap }}>
      {list.map((lang, i) => (
        <React.Fragment key={lang}>
          {i > 0 && sep && <span style={{ color: textColor, opacity: 0.5, fontSize }}>{sep}</span>}
          {label(lang, i === 0)}
        </React.Fragment>
      ))}
    </div>
  );
};
