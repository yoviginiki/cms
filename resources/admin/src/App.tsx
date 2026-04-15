import { Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Dashboard from './pages/Dashboard';
import PagesList from './pages/PagesList';
import PageEditor from './pages/PageEditor';
import PostsList from './pages/PostsList';
import PostEditor from './pages/PostEditor';
import Categories from './pages/Categories';
import Assets from './pages/Assets';
import SiteSettings from './pages/SiteSettings';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: 1,
    },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/sites/:siteId/pages" element={<PagesList />} />
        <Route path="/sites/:siteId/pages/:pageId/edit" element={<PageEditor />} />
        <Route path="/sites/:siteId/posts" element={<PostsList />} />
        <Route path="/sites/:siteId/posts/:postId/edit" element={<PostEditor />} />
        <Route path="/sites/:siteId/categories" element={<Categories />} />
        <Route path="/sites/:siteId/assets" element={<Assets />} />
        <Route path="/sites/:siteId/settings" element={<SiteSettings />} />
      </Routes>
    </QueryClientProvider>
  );
}
