import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Loader2, Save, AlertTriangle, Code } from 'lucide-react';
import { sites } from '@/lib/api';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface Site {
  id: string;
  name: string;
  slug: string;
  status: string;
  seo_title_template?: string;
  seo_description?: string;
  og_image_url?: string;
  head_scripts?: string;
  body_scripts?: string;
  custom_css?: string;
}

type Tab = 'general' | 'seo' | 'custom-code' | 'danger';

declare global {
  interface Window {
    __APP__?: { user?: { role?: string } };
  }
}

export default function SiteSettings() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<Tab>('general');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  // General
  const [name, setName] = useState('');
  const [status, setStatus] = useState('');

  // SEO
  const [seoTitleTemplate, setSeoTitleTemplate] = useState('');
  const [seoDescription, setSeoDescription] = useState('');
  const [ogImageUrl, setOgImageUrl] = useState('');

  // Custom Code
  const [headScripts, setHeadScripts] = useState('');
  const [bodyScripts, setBodyScripts] = useState('');
  const [customCss, setCustomCss] = useState('');

  const userRole = window.__APP__?.user?.role;
  const isAdminOrOwner = userRole === 'admin' || userRole === 'owner';

  const { data: site, isLoading, error } = useQuery<Site>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then(r => r.data.data),
  });

  useEffect(() => {
    if (site) {
      setName(site.name);
      setStatus(site.status);
      setSeoTitleTemplate(site.seo_title_template ?? '');
      setSeoDescription(site.seo_description ?? '');
      setOgImageUrl(site.og_image_url ?? '');
      setHeadScripts(site.head_scripts ?? '');
      setBodyScripts(site.body_scripts ?? '');
      setCustomCss(site.custom_css ?? '');
    }
  }, [site]);

  const updateMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => sites.update(siteId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['site', siteId] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => sites.delete(siteId),
    onSuccess: () => {
      window.location.href = '/admin';
    },
  });

  const saveGeneral = () => updateMutation.mutate({ name, status });
  const saveSeo = () => updateMutation.mutate({
    seo_title_template: seoTitleTemplate,
    seo_description: seoDescription,
    og_image_url: ogImageUrl,
  });
  const saveCustomCode = () => updateMutation.mutate({
    head_scripts: headScripts,
    body_scripts: bodyScripts,
    custom_css: customCss,
  });

  const tabs: { key: Tab; label: string; show: boolean }[] = [
    { key: 'general', label: 'General', show: true },
    { key: 'seo', label: 'SEO', show: true },
    { key: 'custom-code', label: 'Custom Code', show: isAdminOrOwner },
    { key: 'danger', label: 'Danger Zone', show: true },
  ];

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
        Failed to load site settings. Please try again.
      </div>
    );
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Site Settings</h1>
        <p className="mt-1 text-sm text-gray-500">Configure your site</p>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-8">
        <nav className="flex gap-6">
          {tabs.filter(t => t.show).map((tab) => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                activeTab === tab.key
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Success/error messages */}
      {updateMutation.isSuccess && (
        <div className="mb-6 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
          Settings saved successfully.
        </div>
      )}
      {updateMutation.isError && (
        <div className="mb-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
          Failed to save settings. Please try again.
        </div>
      )}

      {/* General Tab */}
      {activeTab === 'general' && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Slug</label>
              <input
                type="text"
                value={site?.slug ?? ''}
                readOnly
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-500 cursor-not-allowed"
              />
              <p className="mt-1 text-xs text-gray-400">Slug cannot be changed after creation.</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="draft">Draft</option>
              </select>
            </div>
            <div className="pt-4 border-t border-gray-100">
              <button
                onClick={saveGeneral}
                disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* SEO Tab */}
      {activeTab === 'seo' && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Title Template</label>
              <input
                type="text"
                value={seoTitleTemplate}
                onChange={(e) => setSeoTitleTemplate(e.target.value)}
                placeholder="%page_title% | %site_name%"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <p className="mt-1 text-xs text-gray-400">Use %page_title% and %site_name% as placeholders.</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Default Meta Description</label>
              <textarea
                value={seoDescription}
                onChange={(e) => setSeoDescription(e.target.value)}
                rows={3}
                placeholder="A brief description of your site for search engines"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Default OG Image URL</label>
              <input
                type="url"
                value={ogImageUrl}
                onChange={(e) => setOgImageUrl(e.target.value)}
                placeholder="https://example.com/og-image.jpg"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div className="pt-4 border-t border-gray-100">
              <button
                onClick={saveSeo}
                disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Custom Code Tab */}
      {activeTab === 'custom-code' && isAdminOrOwner && (
        <div className="max-w-2xl">
          <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
            <AlertTriangle className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-amber-800">Be careful with custom code</p>
              <p className="text-sm text-amber-700 mt-1">
                Injecting scripts and styles can break your site or introduce security vulnerabilities. Only add code from trusted sources.
              </p>
            </div>
          </div>

          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                <Code className="h-4 w-4" />
                Head Scripts
              </label>
              <textarea
                value={headScripts}
                onChange={(e) => setHeadScripts(e.target.value)}
                rows={6}
                placeholder="<!-- Scripts injected before </head> -->"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
              />
            </div>
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                <Code className="h-4 w-4" />
                Body Scripts
              </label>
              <textarea
                value={bodyScripts}
                onChange={(e) => setBodyScripts(e.target.value)}
                rows={6}
                placeholder="<!-- Scripts injected before </body> -->"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
              />
            </div>
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                <Code className="h-4 w-4" />
                Custom CSS
              </label>
              <textarea
                value={customCss}
                onChange={(e) => setCustomCss(e.target.value)}
                rows={8}
                placeholder="/* Custom styles */"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
              />
            </div>
            <div className="pt-4 border-t border-gray-100">
              <button
                onClick={saveCustomCode}
                disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Danger Zone Tab */}
      {activeTab === 'danger' && (
        <div className="max-w-2xl">
          <div className="bg-white rounded-xl border border-red-200 shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-2">Delete Site</h3>
            <p className="text-sm text-gray-500 mb-4">
              Once you delete a site, there is no going back. All pages, posts, and assets will be permanently removed.
            </p>
            <button
              onClick={() => setShowDeleteConfirm(true)}
              className="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700"
            >
              Delete this site
            </button>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={showDeleteConfirm}
        title="Delete site"
        message={`Are you sure you want to delete "${site?.name}"? All content including pages, posts, categories, and assets will be permanently deleted.`}
        confirmText="Delete permanently"
        variant="danger"
        onConfirm={() => deleteMutation.mutate()}
        onClose={() => setShowDeleteConfirm(false)}
      />
    </div>
  );
}
