import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { sites } from '@/lib/api';

/** Site name + branding settings for chrome-block previews (shares the ['site', id] cache). */
export function useSiteBranding() {
  const { siteId = '' } = useParams();
  const { data } = useQuery<any>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then((r: any) => r.data.data),
    enabled: !!siteId,
    staleTime: 60_000,
  });
  const s = data?.settings ?? {};
  return {
    name: (data?.name as string) || 'Site name',
    logoUrl: (s.logo_url as string) || '',
    logoShowName: !!s.logo_show_name,
    tagline: (s.tagline as string) || '',
  };
}
