import { useQuery } from '@tanstack/react-query';
import { pages, posts, blocks } from '@/lib/api';

export function usePageData(siteId: string, pageId: string) {
  const pageQuery = useQuery({
    queryKey: ['page', siteId, pageId],
    queryFn: () => pages.get(siteId, pageId).then((r) => r.data.data),
    refetchOnWindowFocus: true,
  });

  const blocksQuery = useQuery({
    queryKey: ['blocks', 'pages', siteId, pageId],
    queryFn: () => blocks.get(siteId, 'pages', pageId).then((r) => r.data.data),
    refetchOnWindowFocus: false, // blocks managed by editor store — refetch would overwrite unsaved changes
  });

  return {
    page: pageQuery.data,
    blocks: blocksQuery.data,
    isLoading: pageQuery.isLoading || blocksQuery.isLoading,
    error: pageQuery.error || blocksQuery.error,
  };
}

export function usePostData(siteId: string, postId: string) {
  const postQuery = useQuery({
    queryKey: ['post', siteId, postId],
    queryFn: () => posts.get(siteId, postId).then((r) => r.data.data),
    refetchOnWindowFocus: false,
  });

  const blocksQuery = useQuery({
    queryKey: ['blocks', 'posts', siteId, postId],
    queryFn: () => blocks.get(siteId, 'posts', postId).then((r) => r.data.data),
    refetchOnWindowFocus: false,
  });

  return {
    post: postQuery.data,
    blocks: blocksQuery.data,
    isLoading: postQuery.isLoading || blocksQuery.isLoading,
    error: postQuery.error || blocksQuery.error,
  };
}
