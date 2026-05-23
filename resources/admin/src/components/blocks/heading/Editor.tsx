import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField, SelectField, ColorField } from '@/components/editor/fields';
import { pages as pagesApi, posts as postsApi } from '@/lib/api';

export const HeadingEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      {/* ── Content ── */}
      <TextField
        label="Text"
        value={(data.text as string) || ''}
        onChange={(v) => update('text', v)}
        placeholder="Heading text"
      />
      <SelectField
        label="Heading Level"
        value={(data.level as string) || 'h2'}
        onChange={(v) => update('level', v)}
        options={[
          { value: 'h1', label: 'H1' },
          { value: 'h2', label: 'H2' },
          { value: 'h3', label: 'H3' },
          { value: 'h4', label: 'H4' },
          { value: 'h5', label: 'H5' },
          { value: 'h6', label: 'H6' },
        ]}
        helperText="Use only one H1 per page for SEO."
      />

      {/* ── Typography ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <ColorField
        label="Color"
        value={(data.color as string) || ''}
        onChange={(v) => update('color', v)}
      />
      <TextField
        label="Font Size"
        value={(data.fontSize as string) || ''}
        onChange={(v) => update('fontSize', v)}
        placeholder="Leave empty for level default"
        helperText="Override the default size for this heading level"
      />
      <SelectField
        label="Font Weight"
        value={(data.fontWeight as string) || ''}
        onChange={(v) => update('fontWeight', v)}
        options={[
          { value: '', label: 'Default (Bold)' },
          { value: '400', label: 'Normal (400)' },
          { value: '500', label: 'Medium (500)' },
          { value: '600', label: 'Semibold (600)' },
          { value: '700', label: 'Bold (700)' },
          { value: '800', label: 'Extra Bold (800)' },
          { value: '900', label: 'Black (900)' },
        ]}
      />
      <TextField
        label="Line Height"
        value={(data.lineHeight as string) || ''}
        onChange={(v) => update('lineHeight', v)}
        placeholder="e.g. 1.2, 1.5, 48px"
        helperText="Leave empty for default"
      />
      <TextField
        label="Letter Spacing"
        value={(data.letterSpacing as string) || ''}
        onChange={(v) => update('letterSpacing', v)}
        placeholder="e.g. 0.02em, -0.5px"
        helperText="Leave empty for default"
      />
      <SelectField
        label="Text Transform"
        value={(data.textTransform as string) || ''}
        onChange={(v) => update('textTransform', v)}
        options={[
          { value: '', label: 'None' },
          { value: 'uppercase', label: 'UPPERCASE' },
          { value: 'lowercase', label: 'lowercase' },
          { value: 'capitalize', label: 'Capitalize' },
        ]}
      />
      <SelectField
        label="Text Shadow"
        value={(data.textShadow as string) || ''}
        onChange={(v) => update('textShadow', v)}
        options={[
          { value: '', label: 'None' },
          { value: 'sm', label: 'Subtle' },
          { value: 'md', label: 'Medium' },
          { value: 'lg', label: 'Strong' },
          { value: 'outline', label: 'Outline' },
          { value: 'glow', label: 'Glow' },
        ]}
      />
      <SelectField
        label="Text Alignment"
        value={(data.textAlign as string) || ''}
        onChange={(v) => update('textAlign', v)}
        options={[
          { value: '', label: 'Default (inherit)' },
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]}
      />

      {/* ── Link ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Link</div>
      <HeadingLinkPicker data={data} update={update} />
    </div>
  );
};

function HeadingLinkPicker({ data, update }: { data: Record<string, unknown>; update: (f: string, v: unknown) => void }) {
  const { siteId } = useParams<{ siteId: string }>();
  const linkType = (data.linkType as string) || 'none';

  const { data: pagesList } = useQuery<any[]>({
    queryKey: ['pages-list', siteId],
    queryFn: () => siteId ? pagesApi.list(siteId).then((r: any) => r.data.data) : Promise.resolve([]),
    enabled: !!siteId && linkType === 'page',
  });

  const { data: postsList } = useQuery<any[]>({
    queryKey: ['posts-list', siteId],
    queryFn: () => siteId ? postsApi.list(siteId).then((r: any) => r.data.data) : Promise.resolve([]),
    enabled: !!siteId && linkType === 'post',
  });

  return (
    <div className="space-y-2">
      <SelectField
        label="Link To"
        value={linkType}
        onChange={v => { update('linkType', v); if (v === 'none') { update('linkUrl', ''); update('linkPageId', ''); update('linkPostId', ''); } }}
        options={[
          { value: 'none', label: 'No link' },
          { value: 'page', label: 'Page' },
          { value: 'post', label: 'Post' },
          { value: 'custom', label: 'Custom URL' },
        ]}
      />
      {linkType === 'page' && (
        <SelectField
          label="Select Page"
          value={(data.linkPageId as string) || ''}
          onChange={v => {
            update('linkPageId', v);
            const p = pagesList?.find((pg: any) => pg.id === v);
            update('linkUrl', p ? `/${p.slug}` : '');
          }}
          options={[
            { value: '', label: '— Choose —' },
            ...(pagesList || []).map((p: any) => ({ value: p.id, label: p.title || p.slug })),
          ]}
        />
      )}
      {linkType === 'post' && (
        <SelectField
          label="Select Post"
          value={(data.linkPostId as string) || ''}
          onChange={v => {
            update('linkPostId', v);
            const p = postsList?.find((pt: any) => pt.id === v);
            update('linkUrl', p ? `/${p.category?.slug || 'blog'}/${p.slug}` : '');
          }}
          options={[
            { value: '', label: '— Choose —' },
            ...(postsList || []).map((p: any) => ({ value: p.id, label: p.title || p.slug })),
          ]}
        />
      )}
      {linkType === 'custom' && (
        <TextField
          label="URL"
          value={(data.linkUrl as string) || ''}
          onChange={v => update('linkUrl', v)}
          placeholder="https://... or /page-slug"
        />
      )}
      {linkType !== 'none' && (
        <SelectField
          label="Open In"
          value={(data.linkTarget as string) || '_self'}
          onChange={v => update('linkTarget', v)}
          options={[
            { value: '_self', label: 'Same window' },
            { value: '_blank', label: 'New tab' },
          ]}
        />
      )}
    </div>
  );
}
