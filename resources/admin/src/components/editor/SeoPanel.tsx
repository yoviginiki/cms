import { useEffect, useState } from 'react';
import { AssetField } from '@/components/ui/AssetPicker';
import { checkTitleLength, checkDescriptionLength } from '@/lib/seoHelpers';

/**
 * Track F2 — per-page SEO controls shared by the Page and Post editors.
 * Edits the SEO subset of seo_meta (title, description, og_image, canonical,
 * no_index, no_follow) via onPatch; everything else in the blob is untouched.
 */

export interface SeoValues {
  title?: string;
  description?: string;
  og_image?: string;
  canonical?: string;
  no_index?: boolean;
  no_follow?: boolean;
}

const STATUS_CLASS: Record<string, string> = {
  good: 'text-success',
  warn: 'text-warning',
  error: 'text-error',
};

function LengthHint({ check }: { check: { status: string; message: string } }) {
  return <p className={`text-[10px] mt-0.5 ${STATUS_CLASS[check.status] || 'text-base-content/40'}`}>{check.message}</p>;
}

export function SeoPanel({ values, onPatch, fallbackTitle, fallbackDescription, titleTemplate, siteName, urlBase, path }: {
  values: SeoValues;
  onPatch: (patch: Partial<SeoValues>) => void;
  fallbackTitle: string;
  fallbackDescription?: string;
  titleTemplate?: string;
  siteName?: string;
  urlBase: string;
  path: string;
}) {
  // Local drafts so counters update per keystroke; committed to seo_meta on blur.
  const [title, setTitle] = useState(values.title || '');
  const [description, setDescription] = useState(values.description || '');
  const [canonical, setCanonical] = useState(values.canonical || '');
  const [canonicalError, setCanonicalError] = useState('');
  useEffect(() => { setTitle(values.title || ''); }, [values.title]);
  useEffect(() => { setDescription(values.description || ''); }, [values.description]);
  useEffect(() => { setCanonical(values.canonical || ''); }, [values.canonical]);

  const template = titleTemplate || '{title} | {site_name}';
  const effectiveTitle = title || fallbackTitle;
  const fullTitle = template.replace('{title}', effectiveTitle).replace('{site_name}', siteName || '');
  const usingTitleFallback = !title;
  const effectiveDescription = description || fallbackDescription || '';
  const usingDescFallback = !description;
  const autoCanonical = `${urlBase.replace(/\/$/, '')}${path}`;

  const commitCanonical = (raw: string) => {
    const v = raw.trim();
    if (v && !/^https?:\/\/\S+$/.test(v)) {
      setCanonicalError('Must be a full URL starting with http(s)://');
      return;
    }
    setCanonicalError('');
    if (v !== (values.canonical || '')) onPatch({ canonical: v });
  };

  return (
    <div className="space-y-3">
      {/* Google-style snippet preview */}
      <div className="border border-base-300/40 bg-base-100 p-2.5">
        <p className="text-[10px] text-base-content/50 truncate">{values.canonical || autoCanonical}</p>
        <p className={`text-[14px] leading-snug truncate text-primary ${usingTitleFallback ? 'italic opacity-70' : ''}`}>
          {fullTitle || 'Untitled'}
        </p>
        <p className={`text-[11px] text-base-content/60 leading-snug line-clamp-2 ${usingDescFallback ? 'italic opacity-70' : ''}`}>
          {effectiveDescription || 'Auto-generated from page content at publish time.'}
        </p>
      </div>

      {/* SEO title */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">SEO Title</label>
        <input type="text" value={title} className="input input-bordered input-sm w-full text-[12px]"
          placeholder={fallbackTitle || 'Page title'}
          onChange={e => setTitle(e.target.value)}
          onBlur={e => { if (e.target.value !== (values.title || '')) onPatch({ title: e.target.value }); }} />
        <LengthHint check={checkTitleLength(effectiveTitle)} />
      </div>

      {/* Meta description */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Meta Description</label>
        <textarea value={description} className="textarea textarea-bordered textarea-sm w-full text-[12px] min-h-[60px]"
          placeholder={fallbackDescription || 'Auto-generated from page content...'}
          onChange={e => setDescription(e.target.value)}
          onBlur={e => { if (e.target.value !== (values.description || '')) onPatch({ description: e.target.value }); }} />
        <LengthHint check={checkDescriptionLength(effectiveDescription)} />
      </div>

      {/* Social image */}
      <AssetField
        label="Social image (OG / Twitter)"
        value={values.og_image || ''}
        onChange={(url) => onPatch({ og_image: url })}
        accept="image"
      />

      {/* Canonical override */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Canonical URL</label>
        <input type="url" value={canonical} className="input input-bordered input-sm w-full text-[11px] font-mono"
          placeholder={autoCanonical}
          onChange={e => setCanonical(e.target.value)}
          onBlur={e => commitCanonical(e.target.value)} />
        {canonicalError
          ? <p className="text-[10px] text-error mt-0.5">{canonicalError}</p>
          : <p className="text-[10px] text-base-content/25 mt-0.5">Leave empty to use the automatic URL</p>}
      </div>

      {/* Robots */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-1 block">Search Engines</label>
        <label className="flex items-center gap-2 text-[11px] text-base-content/70 cursor-pointer mb-1">
          <input type="checkbox" className="checkbox checkbox-xs" checked={!values.no_index}
            onChange={e => onPatch({ no_index: !e.target.checked })} />
          Allow indexing of this page
        </label>
        <label className="flex items-center gap-2 text-[11px] text-base-content/70 cursor-pointer">
          <input type="checkbox" className="checkbox checkbox-xs" checked={!values.no_follow}
            onChange={e => onPatch({ no_follow: !e.target.checked })} />
          Allow following links on this page
        </label>
        <p className="text-[10px] text-base-content/25 mt-1">Default: index, follow — no robots tag is emitted</p>
      </div>
    </div>
  );
}
