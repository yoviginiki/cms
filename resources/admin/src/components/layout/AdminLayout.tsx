import { useState, useEffect } from 'react';
import { Link, useParams, useLocation } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { LayoutDashboard, FileText, Newspaper, FolderTree, Hash, Menu as MenuIcon, LayoutGrid, Palette, Image, Settings, ChevronLeft, ChevronRight, LogOut, Upload, Bug, GitBranch, BarChart3, Rocket, Loader2, CheckCircle, XCircle, Sun, Moon, BookOpen, Wand2, Users, Archive } from 'lucide-react';
import { publishing, api } from '@/lib/api';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export function AdminLayout({ children }: AdminLayoutProps) {
  const [collapsed, setCollapsed] = useState(false);
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

  const navItems = siteId
    ? [
        { to: `/sites/${siteId}/pages`, icon: FileText, label: 'Pages' },
        { to: `/sites/${siteId}/posts`, icon: Newspaper, label: 'Posts' },
        { to: `/sites/${siteId}/magazines`, icon: BookOpen, label: 'Magazines' },
        { to: `/sites/${siteId}/magazine/wizard`, icon: Wand2, label: 'Wizard' },
        { to: `/sites/${siteId}/categories`, icon: FolderTree, label: 'Categories' },
        { to: `/sites/${siteId}/tags`, icon: Hash, label: 'Tags' },
        { to: `/sites/${siteId}/menus`, icon: MenuIcon, label: 'Menus' },
        { to: `/sites/${siteId}/grids`, icon: LayoutGrid, label: 'Grids' },
        { to: `/sites/${siteId}/theme-engine`, icon: Palette, label: 'Themes' },
        { to: `/sites/${siteId}/assets`, icon: Image, label: 'Assets' },
        { to: `/sites/${siteId}/analytics`, icon: BarChart3, label: 'Analytics' },
        { to: `/sites/${siteId}/graph`, icon: GitBranch, label: 'Graph' },
        { to: `/sites/${siteId}/import`, icon: Upload, label: 'Import' },
        { to: `/sites/${siteId}/settings`, icon: Settings, label: 'Settings' },
      ]
    : [];

  const isActive = (path: string) => location.pathname.startsWith('/admin' + path);

  return (
    <div className="flex h-screen bg-base-200" data-theme={adminTheme}>
      {/* Sidebar */}
      <aside className={`${collapsed ? 'w-14' : 'w-56'} bg-base-100 border-r border-base-300/50 flex flex-col transition-all duration-200`}>
        {/* Logo */}
        <div className="flex items-center justify-between h-12 px-3 border-b border-base-300/30">
          {!collapsed && (
            <Link to="/dashboard" className="text-sm font-medium text-base-content/90 tracking-tight">
              cms
            </Link>
          )}
          <button onClick={() => setCollapsed(!collapsed)}
            className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-base-content/70">
            {collapsed ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
          </button>
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

          {navItems.length > 0 && (
            <>
              {!collapsed && <div className="px-2.5 pt-4 pb-1 text-[10px] font-medium text-base-content/25 uppercase tracking-wider">Content</div>}
              {navItems.slice(0, 6).map((item) => (
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

              {!collapsed && <div className="px-2.5 pt-4 pb-1 text-[10px] font-medium text-base-content/25 uppercase tracking-wider">Design</div>}
              {navItems.slice(6, 9).map((item) => (
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

              {!collapsed && <div className="px-2.5 pt-4 pb-1 text-[10px] font-medium text-base-content/25 uppercase tracking-wider">System</div>}
              {navItems.slice(9).map((item) => (
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
        </nav>

        {/* Bottom */}
        <div className="p-1.5 border-t border-base-300/30 space-y-0.5">
          <a href="/docs" target="_blank"
            className="flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30 transition-colors">
            <BookOpen size={15} strokeWidth={1.5} />
            {!collapsed && 'Docs'}
          </a>
          <a href="/api/v1/cms-export"
            className="flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-[13px] text-base-content/30 hover:text-base-content/50 hover:bg-base-300/30 transition-colors">
            <Archive size={15} strokeWidth={1.5} />
            {!collapsed && 'Export CMS'}
          </a>
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
      <div className="flex-1 flex flex-col overflow-hidden">
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
