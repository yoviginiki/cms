import { useState, useEffect } from 'react';
import { Link, useParams, useLocation } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { LayoutDashboard, FileText, FileWarning, GalleryHorizontalEnd, Newspaper, FolderTree, Hash, Menu as MenuIcon, LayoutGrid, Palette, Settings, ChevronLeft, ChevronRight, LogOut, Upload, Bug, GitBranch, BarChart3, Rocket, Loader2, CheckCircle, XCircle, Sun, Moon, BookOpen, Sparkles, Users, Archive, Download, X, PanelLeft, Wand2, BookMarked, Boxes, Database, LayoutTemplate, ListFilter, FileInput, Webhook } from 'lucide-react';
import { publishing, staleContent, api } from '@/lib/api';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export function AdminLayout({ children }: AdminLayoutProps) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  // Close mobile sidebar on navigation
  const location2 = useLocation();
  useEffect(() => { setMobileOpen(false); }, [location2.pathname]);
  const { siteId: routeSiteId } = useParams();
  const location = useLocation();

  // Persist last visited siteId so the Publish button stays visible on non-site pages
  const [lastSiteId, setLastSiteId] = useState<string | undefined>(
    () => localStorage.getItem('last-site-id') || undefined
  );
  useEffect(() => {
    if (routeSiteId) {
      setLastSiteId(routeSiteId);
      localStorage.setItem('last-site-id', routeSiteId);
    }
  }, [routeSiteId]);
  // Only show site nav when actively viewing a site, not on dashboard/users/debug
  const isOnSitePage = location.pathname.includes('/sites/');
  const siteId = isOnSitePage ? (routeSiteId || lastSiteId) : undefined;
  // But keep lastSiteId for the Publish button on non-site pages
  const publishSiteId = routeSiteId || lastSiteId;
  const [publishStatus, setPublishStatus] = useState<'idle' | 'publishing' | 'success' | 'error'>('idle');
  const [publishMsg, setPublishMsg] = useState('');
  const [publishErrorTime, setPublishErrorTime] = useState<number | null>(null);
  const [exportState, setExportState] = useState<'idle' | 'generating' | 'ready'>('idle');
  const [exportInfo, setExportInfo] = useState<{ size?: number; generated_at?: string }>({});

  // Check export status on mount
  useEffect(() => {
    api.get('/cms-export/status').then(r => {
      const d = r.data?.data;
      if (d?.status === 'ready') {
        setExportState('ready');
        setExportInfo({ size: d.size, generated_at: d.generated_at });
      }
    }).catch(() => {});
  }, []);

  const handleGenerateExport = async () => {
    setExportState('generating');
    try {
      const r = await api.post('/cms-export/generate');
      if (r.data?.data?.status === 'ready') {
        const st = await api.get('/cms-export/status');
        setExportState('ready');
        setExportInfo({ size: st.data?.data?.size, generated_at: st.data?.data?.generated_at });
      }
    } catch {
      setExportState('idle');
    }
  };

  const [adminTheme, setAdminTheme] = useState<'cms-admin' | 'cms-admin-light'>(() => {
    return (localStorage.getItem('admin-theme') as 'cms-admin' | 'cms-admin-light') || 'cms-admin';
  });

  const toggleAdminTheme = () => {
    const next = adminTheme === 'cms-admin' ? 'cms-admin-light' : 'cms-admin';
    setAdminTheme(next);
    localStorage.setItem('admin-theme', next);
    document.documentElement.setAttribute('data-theme', next);
  };

  const publishMutation = useMutation({
    mutationFn: () => {
      if (!publishSiteId) return Promise.reject('No site');
      setPublishStatus('publishing');
      setPublishMsg('');
      return publishing.publish(publishSiteId!);
    },
    onSuccess: () => {
      setPublishStatus('success');
      setPublishMsg('Site queued for rebuild');
      setTimeout(() => setPublishStatus('idle'), 3000);
    },
    onError: (err: any) => {
      setPublishStatus('error');
      const msg = err.response?.data?.message || 'Publish failed';
      setPublishMsg(msg);
      setPublishErrorTime(Math.floor(Date.now() / 1000));
      setTimeout(() => setPublishStatus('idle'), 5000);
    },
  });

  const logoutMutation = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => { window.location.href = '/admin/login'; },
    onError: () => { window.location.href = '/admin/login'; },
  });

  // Stale-content count for the sidebar badge (light poll, site pages only)
  const { data: staleCount = 0 } = useQuery<number>({
    queryKey: ['stale-count', siteId],
    queryFn: () => staleContent.list(siteId!).then(r => r.data.data.count ?? 0),
    enabled: !!siteId,
    refetchInterval: 60_000,
  });

  const mainNav = siteId
    ? [
        { to: `/sites/${siteId}/pages`, icon: FileText, label: 'Pages' },
        { to: `/sites/${siteId}/posts`, icon: Newspaper, label: 'Posts' },
        ...(staleCount > 0
          ? [{ to: `/sites/${siteId}/stale-pages`, icon: FileWarning, label: 'Stale pages', badge: staleCount }]
          : []),
        { to: `/sites/${siteId}/collections`, icon: Database, label: 'Collections' },
        { to: `/sites/${siteId}/queries`, icon: ListFilter, label: 'Queries' },
        { to: `/sites/${siteId}/wizards`, icon: Sparkles, label: 'Wizards' },
        { to: `/sites/${siteId}/webhooks`, icon: Webhook, label: 'Webhooks' },
        { to: `/sites/${siteId}/assets`, icon: Archive, label: 'Media' },
        { to: `/sites/${siteId}/menus`, icon: MenuIcon, label: 'Menus' },
        { to: `/sites/${siteId}/theme-engine`, icon: Palette, label: 'Themes' },
        { to: `/sites/${siteId}/theme-wizard`, icon: Wand2, label: 'Theme Wizard' },
        { to: `/sites/${siteId}/page-wizard`, icon: LayoutTemplate, label: 'Page Wizard' },
        { to: `/sites/${siteId}/form-wizard`, icon: FileInput, label: 'Form Wizard' },
        { to: `/sites/${siteId}/library`, icon: BookMarked, label: 'Library' },
        { to: `/sites/${siteId}/global-sections`, icon: Boxes, label: 'Global Sections' },
        { to: `/sites/${siteId}/style-presets`, icon: Palette, label: 'Style Presets' },
        { to: `/sites/${siteId}/analytics`, icon: BarChart3, label: 'Analytics' },
        { to: `/sites/${siteId}/settings`, icon: Settings, label: 'Settings' },
      ]
    : [];

  const advancedNav = siteId
    ? [
        { to: `/sites/${siteId}/sliders`, icon: GalleryHorizontalEnd, label: 'Sliders' },
        { to: `/sites/${siteId}/magazines`, icon: BookOpen, label: 'Magazines' },
        { to: `/sites/${siteId}/issue-studio`, icon: Sparkles, label: 'Issue Studio' },
        { to: `/sites/${siteId}/categories`, icon: FolderTree, label: 'Categories' },
        { to: `/sites/${siteId}/tags`, icon: Hash, label: 'Tags' },
        { to: `/sites/${siteId}/grids`, icon: LayoutGrid, label: 'Grids' },
        { to: `/sites/${siteId}/templates`, icon: Rocket, label: 'Templates' },
        { to: `/sites/${siteId}/graph`, icon: GitBranch, label: 'Graph' },
        { to: `/sites/${siteId}/import`, icon: Upload, label: 'Import' },
        { to: `/sites/${siteId}/migration`, icon: Download, label: 'Migration' },
      ]
    : [];


  const isActive = (path: string) => location.pathname.startsWith('/admin' + path);

  return (
    <div className="flex h-screen bg-base-200" data-theme={adminTheme}>
      {/* Mobile overlay */}
      {mobileOpen && (
        <div className="fixed inset-0 bg-black/40 z-40 lg:hidden" onClick={() => setMobileOpen(false)} />
      )}

      {/* Sidebar — drawer on mobile, static on desktop */}
      <aside className={`
        ${collapsed ? 'lg:w-14' : 'lg:w-56'}
        fixed lg:static inset-y-0 left-0 z-50 w-64
        bg-base-100 border-r border-base-300/50 flex flex-col transition-all duration-200
        ${mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}
      `}>
        {/* Logo */}
        <div className="flex items-center justify-between h-12 px-3 border-b border-base-300/30">
          {!collapsed && (
            <Link to="/dashboard" className="text-sm font-medium text-base-content/90 tracking-tight">
              cms
            </Link>
          )}
          <div className="flex items-center gap-1">
            <button onClick={() => setMobileOpen(false)}
              className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-base-content/70 lg:hidden">
              <X size={14} />
            </button>
            <button onClick={() => setCollapsed(!collapsed)}
              className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-base-content/70 hidden lg:flex">
              {collapsed ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
            </button>
          </div>
        </div>

        {/* Navigation */}
        <nav className="flex-1 py-2 px-1.5 overflow-y-auto space-y-0.5">
          <Link to="/dashboard"
            className={`flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] transition-colors ${
              location.pathname === '/admin/dashboard'
                ? 'bg-primary/10 text-primary'
                : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30'
            }`}>
            <LayoutDashboard size={15} strokeWidth={1.5} />
            {!collapsed && 'Dashboard'}
          </Link>
          <Link to="/users"
            className={`flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] transition-colors ${
              location.pathname.startsWith('/admin/users')
                ? 'bg-primary/10 text-primary'
                : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30'
            }`}>
            <Users size={15} strokeWidth={1.5} />
            {!collapsed && 'Users'}
          </Link>

          {mainNav.length > 0 && (
            <>
              {mainNav.map((item) => (
                <Link key={item.to} to={item.to}
                  className={`flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] transition-colors ${
                    isActive(item.to)
                      ? 'bg-primary/10 text-primary'
                      : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30'
                  }`}>
                  <item.icon size={15} strokeWidth={1.5} />
                  {!collapsed && item.label}
                  {'badge' in item && item.badge != null && (
                    <span className={`badge badge-warning badge-xs font-semibold ${collapsed ? '-ml-1' : 'ml-auto'}`}>
                      {item.badge}
                    </span>
                  )}
                </Link>
              ))}

              {advancedNav.length > 0 && (
                <>
                  {!collapsed && <div className="px-2.5 pt-4 pb-1 text-[10px] font-medium text-base-content/25 uppercase tracking-wider">Advanced</div>}
                  {collapsed && <div className="border-t border-base-300/20 my-1.5" />}
                  {advancedNav.map((item) => (
                    <Link key={item.to} to={item.to}
                      className={`flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] transition-colors ${
                        isActive(item.to)
                          ? 'bg-primary/10 text-primary'
                          : 'text-base-content/50 hover:text-base-content/80 hover:bg-base-300/30'
                      }`}>
                      <item.icon size={15} strokeWidth={1.5} />
                      {!collapsed && item.label}
                    </Link>
                  ))}
                </>
              )}
            </>
          )}
        </nav>

        {/* Bottom */}
        <div className="p-1.5 border-t border-base-300/30 space-y-0.5">
          <a href="/docs" target="_blank"
            className="flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30 transition-colors">
            <BookOpen size={15} strokeWidth={1.5} />
            {!collapsed && 'Docs'}
          </a>
          <a href="/docs/page-generation-guide.md" target="_blank"
            className="flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30 transition-colors">
            <FileText size={15} strokeWidth={1.5} />
            {!collapsed && 'Page Guide'}
          </a>
          {!collapsed ? (
            <div className="px-2.5 py-1.5 space-y-1">
              <button onClick={handleGenerateExport} disabled={exportState === 'generating'}
                className="flex items-center gap-2 w-full px-2 py-1 rounded-md text-[12px] text-base-content/40 hover:text-base-content/70 hover:bg-base-300/30 transition-colors disabled:opacity-50">
                {exportState === 'generating' ? <Loader2 size={13} className="animate-spin" /> : <Archive size={13} strokeWidth={1.5} />}
                {exportState === 'generating' ? 'Generating...' : 'Generate Export'}
              </button>
              {exportState === 'ready' && (
                <a href="/api/v1/cms-export/download"
                  className="flex items-center gap-2 w-full px-2 py-1 rounded-md text-[11px] text-success hover:bg-success/10 transition-colors">
                  <Download size={12} strokeWidth={1.5} />
                  Download ({exportInfo.size ? (exportInfo.size / 1024 / 1024).toFixed(1) + ' MB' : '...'})
                </a>
              )}
            </div>
          ) : (
            <button onClick={handleGenerateExport} disabled={exportState === 'generating'}
              className="flex items-center justify-center w-full px-2.5 py-1.5 rounded-md text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30 transition-colors"
              title="Generate CMS Export">
              {exportState === 'generating' ? <Loader2 size={15} className="animate-spin" /> : <Archive size={15} strokeWidth={1.5} />}
            </button>
          )}
          <button onClick={toggleAdminTheme}
            className="flex items-center gap-2.5 w-full px-2.5 py-1.5 rounded-md text-[13px] text-base-content/40 hover:text-base-content/70 hover:bg-base-300/30 transition-colors"
            title={adminTheme === 'cms-admin' ? 'Switch to light mode' : 'Switch to dark mode'}>
            {adminTheme === 'cms-admin' ? <Sun size={15} strokeWidth={1.5} /> : <Moon size={15} strokeWidth={1.5} />}
            {!collapsed && (adminTheme === 'cms-admin' ? 'Light mode' : 'Dark mode')}
          </button>
          <Link to="/debug"
            className={`flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] transition-colors ${
              location.pathname.startsWith('/admin/debug')
                ? 'bg-warning/10 text-warning'
                : 'text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30'
            }`}>
            <Bug size={15} strokeWidth={1.5} />
            {!collapsed && 'Debug'}
          </Link>
          <button onClick={() => logoutMutation.mutate()} className="flex items-center gap-2.5 w-full px-2.5 py-1.5 rounded-md text-[13px] text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30 transition-colors">
            <LogOut size={15} strokeWidth={1.5} />
            {!collapsed && 'Logout'}
          </button>
        </div>
      </aside>

      {/* Main area */}
      <div className="flex-1 flex flex-col overflow-hidden min-w-0">
        {/* Mobile top bar with hamburger */}
        <div className="flex items-center h-12 px-3 bg-base-100 border-b border-base-300/30 lg:hidden shrink-0">
          <button onClick={() => setMobileOpen(true)}
            className="btn btn-ghost btn-sm btn-square text-base-content/60">
            <PanelLeft size={18} />
          </button>
          <span className="text-sm font-medium text-base-content/70 ml-2">CMS</span>
        </div>

        {/* Top bar */}
        {publishSiteId && (
          <div className="flex items-center justify-between h-12 px-4 bg-base-100 border-b border-base-300/30 shrink-0">
            {/* Status */}
            <div className="text-[13px]">
              {publishStatus === 'success' && publishMsg && (
                <span className="text-success flex items-center gap-1.5"><CheckCircle size={13} /> {publishMsg}</span>
              )}
              {publishStatus === 'error' && publishMsg && (
                <span className="text-error flex items-center gap-1.5">
                  <XCircle size={13} /> {publishMsg}
                  {publishErrorTime && (
                    <Link to={`/debug?since=${publishErrorTime}`} className="underline opacity-70 hover:opacity-100 ml-1">View logs</Link>
                  )}
                </span>
              )}
            </div>
            <button
              onClick={() => publishMutation.mutate()}
              disabled={publishStatus === 'publishing'}
              className={`btn btn-sm gap-1.5 text-[12px] font-medium ${
                publishStatus === 'success' ? 'btn-success' :
                publishStatus === 'error' ? 'btn-error' :
                publishStatus === 'publishing' ? 'btn-primary opacity-80' :
                'btn-primary'
              }`}
            >
              {publishStatus === 'publishing' && <Loader2 size={13} className="animate-spin" />}
              {publishStatus === 'success' && <CheckCircle size={13} />}
              {publishStatus === 'error' && <XCircle size={13} />}
              {publishStatus === 'idle' && <Rocket size={13} />}
              {publishStatus === 'idle' && 'Publish'}
              {publishStatus === 'publishing' && 'Publishing...'}
              {publishStatus === 'success' && 'Published'}
              {publishStatus === 'error' && 'Retry'}
            </button>
          </div>
        )}

        <main className="flex-1 overflow-y-auto p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
