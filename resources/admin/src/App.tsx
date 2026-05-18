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
import MagazineList from './pages/MagazineList';
import MagazineEditorV2 from './pages/MagazineEditorV2';
import Assets from './pages/Assets';
import SiteSettings from './pages/SiteSettings';
import ImportPage from './pages/ImportPage';
import Tags from './pages/Tags';
import Menus from './pages/Menus';
import MenuEditor from './pages/MenuEditor';
import Users from './pages/Users';
import Grids from './pages/Grids';
import GridEditor from './pages/GridEditor';
import GridAssignments from './pages/GridAssignments';
import Analytics from './pages/Analytics';
import ContentGraph from './pages/ContentGraph';
import DebugConsole from './pages/DebugConsole';
import ThemeEngine from './pages/ThemeEngine';
import ThemeEditorPage from './pages/ThemeEditor';
import ThemeStudio from './pages/ThemeStudio';
import Templates from './pages/Templates';
import TemplateEditor from './pages/TemplateEditor';
import Login from './pages/Login';
import SessionsListPage from './pages/wizard/SessionsListPage';
import WizardPage from './pages/wizard/WizardPage';

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
          <Route path="/login" element={<Login />} />

          {/* Pages with sidebar layout */}
          <Route path="/dashboard" element={<LayoutRoute><Dashboard /></LayoutRoute>} />
          <Route path="/sites/:siteId/pages" element={<LayoutRoute><PagesList /></LayoutRoute>} />
          <Route path="/sites/:siteId/posts" element={<LayoutRoute><PostsList /></LayoutRoute>} />
          <Route path="/sites/:siteId/magazines" element={<LayoutRoute><MagazineList /></LayoutRoute>} />
          <Route path="/sites/:siteId/magazine/wizard" element={<LayoutRoute><SessionsListPage /></LayoutRoute>} />
          <Route path="/sites/:siteId/magazine/wizard/:id" element={<WizardPage />} />
          <Route path="/sites/:siteId/magazines/:magazineId/edit" element={<MagazineEditorV2 />} />
          <Route path="/sites/:siteId/categories" element={<LayoutRoute><Categories /></LayoutRoute>} />
          <Route path="/sites/:siteId/tags" element={<LayoutRoute><Tags /></LayoutRoute>} />
          <Route path="/sites/:siteId/menus" element={<LayoutRoute><Menus /></LayoutRoute>} />
          <Route path="/sites/:siteId/menus/:menuId/edit" element={<LayoutRoute><MenuEditor /></LayoutRoute>} />
          <Route path="/sites/:siteId/grids" element={<LayoutRoute><Grids /></LayoutRoute>} />
          <Route path="/sites/:siteId/grids/assignments" element={<LayoutRoute><GridAssignments /></LayoutRoute>} />
          <Route path="/sites/:siteId/grids/:gridId/edit" element={<GridEditor />} />
          <Route path="/sites/:siteId/assets" element={<LayoutRoute><Assets /></LayoutRoute>} />
          <Route path="/sites/:siteId/templates" element={<LayoutRoute><Templates /></LayoutRoute>} />
          <Route path="/sites/:siteId/templates/:templateId/edit" element={<TemplateEditor />} />
          <Route path="/sites/:siteId/theme-engine" element={<LayoutRoute><ThemeEngine /></LayoutRoute>} />
          <Route path="/sites/:siteId/theme-engine/:themeId" element={<ThemeEditorPage />} />
          <Route path="/sites/:siteId/theme-engine/:themeId/studio" element={<ThemeStudio />} />
          <Route path="/sites/:siteId/settings" element={<LayoutRoute><SiteSettings /></LayoutRoute>} />
          <Route path="/sites/:siteId/import" element={<LayoutRoute><ImportPage /></LayoutRoute>} />
          <Route path="/sites/:siteId/analytics" element={<LayoutRoute><Analytics /></LayoutRoute>} />
          <Route path="/sites/:siteId/graph" element={<LayoutRoute><ContentGraph /></LayoutRoute>} />
          <Route path="/users" element={<LayoutRoute><Users /></LayoutRoute>} />
          <Route path="/debug" element={<LayoutRoute><DebugConsole /></LayoutRoute>} />

          {/* Full-screen editors (no sidebar) */}
          <Route path="/sites/:siteId/pages/:pageId/edit" element={<PageEditor />} />
          <Route path="/sites/:siteId/posts/:postId/edit" element={<PostEditor />} />
        </Routes>
      </ToastProvider>
    </QueryClientProvider>
  );
}
