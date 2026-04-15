import { Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AdminLayout } from './components/layout/AdminLayout';
import { ToastProvider } from './components/ui/Toast';
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

function LayoutRoute({ children }: { children: React.ReactNode }) {
  return <AdminLayout>{children}</AdminLayout>;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />

          {/* Pages with sidebar layout */}
          <Route path="/dashboard" element={<LayoutRoute><Dashboard /></LayoutRoute>} />
          <Route path="/sites/:siteId/pages" element={<LayoutRoute><PagesList /></LayoutRoute>} />
          <Route path="/sites/:siteId/posts" element={<LayoutRoute><PostsList /></LayoutRoute>} />
          <Route path="/sites/:siteId/categories" element={<LayoutRoute><Categories /></LayoutRoute>} />
          <Route path="/sites/:siteId/assets" element={<LayoutRoute><Assets /></LayoutRoute>} />
          <Route path="/sites/:siteId/settings" element={<LayoutRoute><SiteSettings /></LayoutRoute>} />

          {/* Full-screen editors (no sidebar) */}
          <Route path="/sites/:siteId/pages/:pageId/edit" element={<PageEditor />} />
          <Route path="/sites/:siteId/posts/:postId/edit" element={<PostEditor />} />
        </Routes>
      </ToastProvider>
    </QueryClientProvider>
  );
}
