import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { TextField } from './TextField';
import { SelectField } from './SelectField';
import { pages as pagesApi, posts as postsApi } from '@/lib/api';

/**
 * Link destination picker shared by blocks: a site page, a site post (article)
 * or a custom URL. Writes linkType / linkUrl / linkPageId / linkPostId /
 * linkTarget on the block data.
 */
export function LinkTargetPicker({ data, update, allowNone = true }: {
  data: Record<string, unknown>;
  update: (f: string, v: unknown) => void;
  allowNone?: boolean;
}) {
  const { siteId } = useParams<{ siteId: string }>();
  const linkType = (data.linkType as string) || (allowNone ? 'none' : 'custom');

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
          ...(allowNone ? [{ value: 'none', label: 'No link' }] : []),
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
