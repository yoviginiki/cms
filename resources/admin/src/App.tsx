import { Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Suspense, lazy } from 'react';
import { AdminLayout } from './components/layout/AdminLayout';
import { ToastProvider } from './components/ui/Toast';
import { ErrorBoundary } from './components/ui/ErrorBoundary';
import { Loader2 } from 'lucide-react';

// Eagerly loaded (critical path)
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';

// Lazy loaded (route-level code splitting)
const PagesList = lazy(() => import('./pages/PagesList'));
const PageEditor = lazy(() => import('./pages/PageEditor'));
const PostsList = lazy(() => import('./pages/PostsList'));
const PostEditor = lazy(() => import('./pages/PostEditor'));
const Categories = lazy(() => import('./pages/Categories'));
const MagazineList = lazy(() => import('./pages/MagazineList'));
// W0-1 (user decision 2026-07-04): legacy magazine editor FROZEN read-only —
// no legacy magazines to migrate; DTP editor is the single magazine editor.
const FrozenLegacyEditor = () => (
  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh', flexDirection: 'column', gap: 12, fontFamily: 'Inter, sans-serif' }}>
    <h2 style={{ fontSize: 18, fontWeight: 600 }}>Legacy magazine editor is frozen</h2>
    <p style={{ fontSize: 13, opacity: 0.6, maxWidth: 420, textAlign: 'center' }}>
      Magazine editing moved to the DTP editor (Magazine Issues). This legacy
      editor was retired after the flow-engine rebuild — no data was migrated
      because no legacy magazines exist.
    </p>
    <a href="/admin/" style={{ fontSize: 13, color: '#3b82f6' }}>Back to dashboard</a>
  </div>
);
const Assets = lazy(() => import('./pages/Assets'));
const SiteSettings = lazy(() => import('./pages/SiteSettings'));
const ImportPage = lazy(() => import('./pages/ImportPage'));
const Tags = lazy(() => import('./pages/Tags'));
const Menus = lazy(() => import('./pages/Menus'));
const MenuEditor = lazy(() => import('./pages/MenuEditor'));
const Users = lazy(() => import('./pages/Users'));
const Grids = lazy(() => import('./pages/Grids'));
const GridEditor = lazy(() => import('./pages/GridEditor'));
const GridAssignments = lazy(() => import('./pages/GridAssignments'));
const Analytics = lazy(() => import('./pages/Analytics'));
const ContentGraph = lazy(() => import('./pages/ContentGraph'));
const DebugConsole = lazy(() => import('./pages/DebugConsole'));
const ThemeEngine = lazy(() => import('./pages/ThemeEngine'));
const ThemeWizardPage = lazy(() => import('./pages/ThemeWizardPage'));
const LibraryPage = lazy(() => import('./pages/LibraryPage'));
const GlobalSectionsList = lazy(() => import('./pages/GlobalSectionsList'));
const StylePresetsList = lazy(() => import('./pages/StylePresetsList'));
const ThemeEditorPage = lazy(() => import('./pages/ThemeEditor'));
const ThemeStudio = lazy(() => import('./pages/ThemeStudio'));
const Templates = lazy(() => import('./pages/Templates'));
const TemplateEditor = lazy(() => import('./pages/TemplateEditor'));
const DtpPrototypeShell = lazy(() => import('./components/magazine/prototypes/dtp/DtpPrototypeShell'));
const DtpEditorBeta = lazy(() => import('./pages/DtpEditorBeta'));
const IssueStudioListPage = lazy(() => import('./pages/issue-studio/IssueStudioListPage'));
const IssueStudioPage = lazy(() => import('./pages/issue-studio/IssueStudioPage'));
const StalePages = lazy(() => import('./pages/StalePages'));
const SlidersList = lazy(() => import('./pages/SlidersList'));
const SliderEditor = lazy(() => import('./pages/SliderEditor'));

function LazyFallback() {
  return <div className="flex items-center justify-center h-64"><Loader2 className="h-6 w-6 animate-spin text-base-content/20" /></div>;
}

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
        <ErrorBoundary>
        <Suspense fallback={<LazyFallback />}>
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/login" element={<Login />} />

          {/* Pages with sidebar layout */}
          <Route path="/dashboard" element={<LayoutRoute><Dashboard /></LayoutRoute>} />
          <Route path="/sites/:siteId/pages" element={<LayoutRoute><PagesList /></LayoutRoute>} />
          <Route path="/sites/:siteId/stale-pages" element={<LayoutRoute><StalePages /></LayoutRoute>} />
          <Route path="/sites/:siteId/sliders" element={<LayoutRoute><SlidersList /></LayoutRoute>} />
          <Route path="/sites/:siteId/sliders/:sliderId/edit" element={<SliderEditor />} />
          <Route path="/sites/:siteId/posts" element={<LayoutRoute><PostsList /></LayoutRoute>} />
          <Route path="/sites/:siteId/magazines" element={<LayoutRoute><MagazineList /></LayoutRoute>} />
          <Route path="/sites/:siteId/issue-studio" element={<LayoutRoute><IssueStudioListPage /></LayoutRoute>} />
          <Route path="/sites/:siteId/issue-studio/:id" element={<LayoutRoute><IssueStudioPage /></LayoutRoute>} />
          <Route path="/sites/:siteId/magazines/:magazineId/edit" element={<FrozenLegacyEditor />} />
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
          <Route path="/sites/:siteId/theme-wizard" element={<LayoutRoute><ThemeWizardPage /></LayoutRoute>} />
          <Route path="/sites/:siteId/library" element={<LayoutRoute><LibraryPage /></LayoutRoute>} />
          <Route path="/sites/:siteId/global-sections" element={<LayoutRoute><GlobalSectionsList /></LayoutRoute>} />
          <Route path="/sites/:siteId/style-presets" element={<LayoutRoute><StylePresetsList /></LayoutRoute>} />
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

          {/* Dev prototype: DTP Canvas (M1) — intentionally production-accessible for stakeholder review */}
          <Route path="/sites/:siteId/magazine/dtp-prototype" element={<DtpPrototypeShell />} />

          {/* Beta DTP Editor — connected to real API, feature-flagged on backend */}
          <Route path="/sites/:siteId/magazine-issues/:issueId/dtp-editor" element={<DtpEditorBeta />} />
        </Routes>
        </Suspense>
        </ErrorBoundary>
      </ToastProvider>
    </QueryClientProvider>
  );
}
