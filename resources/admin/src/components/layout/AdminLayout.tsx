import { useState } from 'react';
import { Link, useParams, useLocation } from 'react-router-dom';
import { LayoutDashboard, FileText, Newspaper, FolderTree, Image, Settings, ChevronLeft, ChevronRight, LogOut } from 'lucide-react';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export function AdminLayout({ children }: AdminLayoutProps) {
  const [collapsed, setCollapsed] = useState(false);
  const { siteId } = useParams();
  const location = useLocation();

  const navItems = siteId
    ? [
        { to: `/sites/${siteId}/pages`, icon: FileText, label: 'Pages' },
        { to: `/sites/${siteId}/posts`, icon: Newspaper, label: 'Posts' },
        { to: `/sites/${siteId}/categories`, icon: FolderTree, label: 'Categories' },
        { to: `/sites/${siteId}/assets`, icon: Image, label: 'Assets' },
        { to: `/sites/${siteId}/settings`, icon: Settings, label: 'Settings' },
      ]
    : [];

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Sidebar */}
      <aside className={`${collapsed ? 'w-16' : 'w-60'} bg-white border-r border-gray-200 flex flex-col transition-all duration-200`}>
        <div className="flex items-center justify-between p-4 border-b border-gray-100">
          {!collapsed && <Link to="/dashboard" className="text-lg font-bold text-gray-800">CMS</Link>}
          <button onClick={() => setCollapsed(!collapsed)} className="p-1 hover:bg-gray-100 rounded">
            {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
          </button>
        </div>

        <nav className="flex-1 py-4 space-y-1 px-2">
          <Link
            to="/dashboard"
            className={`flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors ${
              location.pathname === '/admin/dashboard' ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'
            }`}
          >
            <LayoutDashboard size={18} />
            {!collapsed && 'Dashboard'}
          </Link>

          {navItems.length > 0 && (
            <>
              {!collapsed && <div className="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 uppercase">Site</div>}
              {navItems.map((item) => (
                <Link
                  key={item.to}
                  to={item.to}
                  className={`flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                    location.pathname.startsWith('/admin' + item.to) ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'
                  }`}
                >
                  <item.icon size={18} />
                  {!collapsed && item.label}
                </Link>
              ))}
            </>
          )}
        </nav>

        <div className="p-2 border-t border-gray-100">
          <button className="flex items-center gap-3 w-full px-3 py-2 rounded-md text-sm text-gray-500 hover:bg-gray-100">
            <LogOut size={18} />
            {!collapsed && 'Logout'}
          </button>
        </div>
      </aside>

      {/* Main */}
      <main className="flex-1 overflow-y-auto">
        {children}
      </main>
    </div>
  );
}
