import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Loader2, Save, AlertTriangle, Code, Home, LayoutGrid } from 'lucide-react';
import { api, sites, pages as pagesApi, grids as gridsApi } from '@/lib/api';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface SiteData {
  id: string;
  name: string;
  slug: string;
  status: string;
  settings: Record<string, unknown>;
  seo_defaults: Record<string, unknown>;
}

interface PageItem {
  id: string;
  title: string;
  slug: string;
  status: string;
}

type Tab = 'general' | 'branding' | 'front-page' | 'seo' | 'files' | 'deploy' | 'custom-code' | 'global-styles' | 'languages' | 'forms' | 'ai' | 'magazine' | 'danger';

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
  const [autoPublish, setAutoPublish] = useState(true);

  // Branding
  const [logoUrl, setLogoUrl] = useState('');
  const [tagline, setTagline] = useState('');
  const [footerText, setFooterText] = useState('');
  const [footerCopyright, setFooterCopyright] = useState('');
  const [socialLinks, setSocialLinks] = useState<Record<string, string>>({});

  // Front Page
  const [homepageType, setHomepageType] = useState<'page' | 'grid' | 'blog'>('page');
  const [homepageId, setHomepageId] = useState('');
  const [homepageGridId, setHomepageGridId] = useState('');
  const [blogPageId, setBlogPageId] = useState('');

  // SEO
  const [seoTitleTemplate, setSeoTitleTemplate] = useState('');
  const [seoDescription, setSeoDescription] = useState('');
  const [ogImageUrl, setOgImageUrl] = useState('');

  // Languages
  const [siteLanguages, setSiteLanguages] = useState<string[]>([]);
  const [defaultLanguage, setDefaultLanguage] = useState('en');

  // Analytics
  const [gaId, setGaId] = useState('');

  // Custom Code
  const [headScripts, setHeadScripts] = useState('');
  const [bodyScripts, setBodyScripts] = useState('');
  const [customCss, setCustomCss] = useState('');

  // Global Styles
  const [globalFontFamily, setGlobalFontFamily] = useState('');
  const [globalFontSize, setGlobalFontSize] = useState('');
  const [globalLineHeight, setGlobalLineHeight] = useState('');
  const [globalTextColor, setGlobalTextColor] = useState('');
  const [globalBgColor, setGlobalBgColor] = useState('');
  const [globalLinkColor, setGlobalLinkColor] = useState('');
  const [globalContainerWidth, setGlobalContainerWidth] = useState('');
  const [globalContainerPadding, setGlobalContainerPadding] = useState('');

  // AI
  const [anthropicKey, setAnthropicKey] = useState('');

  // Magazine viewer
  const [magTransition, setMagTransition] = useState('turn');
  const [magSpread, setMagSpread] = useState('spread');
  const [magBg, setMagBg] = useState('#0a0a0a');
  const [magSpeed, setMagSpeed] = useState(500);
  const [magPageNumbers, setMagPageNumbers] = useState(true);
  const [magPnPosition, setMagPnPosition] = useState('bottom');
  const [magPnAlign, setMagPnAlign] = useState('outer');
  const [magPnSize, setMagPnSize] = useState('9');
  const [openaiKey, setOpenaiKey] = useState('');

  // Files
  const DEFAULT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'txt', 'md', 'mp3', 'mp4', 'mov', 'mpg', 'zip', 'rar'];
  const [allowedExtensions, setAllowedExtensions] = useState<string[]>(DEFAULT_EXTENSIONS);
  const [newExt, setNewExt] = useState('');

  // Deploy
  const [deployMethod, setDeployMethod] = useState<'local' | 'ssh' | 'zip_only'>('local');
  const [sshHost, setSshHost] = useState('');
  const [sshUser, setSshUser] = useState('');
  const [sshPath, setSshPath] = useState('');
  const [sshPort, setSshPort] = useState(22);
  const [sshKey, setSshKey] = useState('');

  const userRole = window.__APP__?.user?.role;
  const isAdminOrOwner = userRole === 'admin' || userRole === 'owner';

  const { data: site, isLoading, error } = useQuery<SiteData>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then(r => r.data.data),
  });

  const { data: sitePages } = useQuery<PageItem[]>({
    queryKey: ['pages-for-settings', siteId],
    queryFn: () => pagesApi.list(siteId).then(r => r.data.data),
  });

  const { data: siteGrids } = useQuery<Array<{ id: string; name: string; slug: string }>>({
    queryKey: ['grids-for-settings', siteId],
    queryFn: () => gridsApi.list(siteId).then((r: any) => r.data.data),
  });

  useEffect(() => {
    if (site) {
      setName(site.name);
      setStatus(site.status);
      setAutoPublish((site.settings?.auto_publish as boolean) ?? true);
      setLogoUrl((site.settings?.logo_url as string) ?? '');
      setTagline((site.settings?.tagline as string) ?? '');
      setFooterText((site.settings?.footer_text as string) ?? '');
      setFooterCopyright((site.settings?.footer_copyright as string) ?? '');
      setSocialLinks((site.settings?.social_links as Record<string, string>) ?? {});
      setHomepageType((site.settings?.homepage_type as 'page' | 'grid' | 'blog') ?? 'page');
      setHomepageId((site.settings?.homepage_id as string) ?? '');
      setHomepageGridId((site.settings?.homepage_grid_id as string) ?? '');
      setBlogPageId((site.settings?.blog_page_id as string) ?? '');
      setSeoTitleTemplate((site.seo_defaults?.title_template as string) ?? '');
      setSeoDescription((site.seo_defaults?.description as string) ?? '');
      setOgImageUrl((site.seo_defaults?.og_image as string) ?? '');
      setSiteLanguages((site.settings?.languages as string[]) ?? []);
      setDefaultLanguage((site.settings?.default_language as string) ?? 'en');
      setGaId((site.settings?.google_analytics_id as string) ?? '');
      setHeadScripts((site.settings?.head_scripts as string) ?? '');
      setBodyScripts((site.settings?.body_scripts as string) ?? '');
      setCustomCss((site.settings?.custom_css as string) ?? '');
      setGlobalFontFamily((site.settings?.global_font_family as string) ?? '');
      setGlobalFontSize((site.settings?.global_font_size as string) ?? '');
      setGlobalLineHeight((site.settings?.global_line_height as string) ?? '');
      setGlobalTextColor((site.settings?.global_text_color as string) ?? '');
      setGlobalBgColor((site.settings?.global_bg_color as string) ?? '');
      setGlobalLinkColor((site.settings?.global_link_color as string) ?? '');
      setGlobalContainerWidth((site.settings?.global_container_width as string) ?? '');
      setGlobalContainerPadding((site.settings?.global_container_padding as string) ?? '');
      setAnthropicKey((site.settings?.anthropic_api_key as string) ?? '');
      setMagTransition((site.settings?.mag_transition as string) ?? 'turn');
      setMagSpread((site.settings?.mag_spread as string) ?? 'spread');
      setMagBg((site.settings?.mag_bg as string) ?? '#0a0a0a');
      setMagSpeed(Number(site.settings?.mag_speed) || 500);
      setMagPageNumbers(site.settings?.mag_page_numbers !== false);
      setMagPnPosition((site.settings?.mag_pn_position as string) ?? 'bottom');
      setMagPnAlign((site.settings?.mag_pn_align as string) ?? 'outer');
      setMagPnSize((site.settings?.mag_pn_size as string) ?? '9');
      setOpenaiKey((site.settings?.openai_api_key as string) ?? '');
      setDeployMethod((site.settings?.deploy_method as 'local' | 'ssh' | 'zip_only') ?? 'local');
      setSshHost((site.settings?.deploy_ssh_host as string) ?? '');
      setSshUser((site.settings?.deploy_ssh_user as string) ?? '');
      setSshPath((site.settings?.deploy_ssh_path as string) ?? '');
      setSshPort(Number(site.settings?.deploy_ssh_port) || 22);
      setSshKey((site.settings?.deploy_ssh_key as string) ?? '');
      setAllowedExtensions((site.settings?.allowed_extensions as string[]) ?? DEFAULT_EXTENSIONS);
    }
  }, [site]);

  const updateMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => sites.update(siteId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['site', siteId] });
      queryClient.invalidateQueries({ queryKey: ['pages-for-settings', siteId] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => sites.delete(siteId),
    onSuccess: () => { window.location.href = '/admin'; },
  });

  const saveGeneral = () => updateMutation.mutate({
    name,
    status,
    settings: { ...(site?.settings || {}), auto_publish: autoPublish },
  });

  const saveBranding = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      logo_url: logoUrl || null,
      tagline: tagline || null,
      footer_text: footerText || null,
      footer_copyright: footerCopyright || null,
      social_links: socialLinks,
    },
  });

  const saveFrontPage = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      homepage_type: homepageType,
      homepage_id: homepageType === 'page' ? (homepageId || null) : null,
      homepage_grid_id: homepageType === 'grid' ? (homepageGridId || null) : null,
      blog_page_id: blogPageId || null,
    },
  });

  const saveSeo = () => updateMutation.mutate({
    seo_defaults: {
      ...(site?.seo_defaults || {}),
      title_template: seoTitleTemplate,
      description: seoDescription,
      og_image: ogImageUrl,
    },
  });

  const saveCustomCode = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      google_analytics_id: gaId,
      head_scripts: headScripts,
      body_scripts: bodyScripts,
      custom_css: customCss,
    },
  });

  const saveGlobalStyles = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      global_font_family: globalFontFamily,
      global_font_size: globalFontSize,
      global_line_height: globalLineHeight,
      global_text_color: globalTextColor,
      global_bg_color: globalBgColor,
      global_link_color: globalLinkColor,
      global_container_width: globalContainerWidth,
      global_container_padding: globalContainerPadding,
    },
  });

  const saveLanguages = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      languages: siteLanguages,
      default_language: defaultLanguage,
    },
  });

  const saveMagazine = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      mag_transition: magTransition,
      mag_spread: magSpread,
      mag_bg: magBg,
      mag_speed: magSpeed,
      mag_page_numbers: magPageNumbers,
      mag_pn_position: magPnPosition,
      mag_pn_align: magPnAlign,
      mag_pn_size: magPnSize,
    },
  });

  const saveAi = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      anthropic_api_key: anthropicKey || null,
      openai_api_key: openaiKey || null,
    },
  });

  const saveFiles = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      allowed_extensions: allowedExtensions,
    },
  });

  const saveDeploy = () => updateMutation.mutate({
    settings: {
      ...(site?.settings || {}),
      deploy_method: deployMethod,
      deploy_ssh_host: deployMethod === 'ssh' ? sshHost : null,
      deploy_ssh_user: deployMethod === 'ssh' ? sshUser : null,
      deploy_ssh_path: deployMethod === 'ssh' ? sshPath : null,
      deploy_ssh_port: deployMethod === 'ssh' ? sshPort : null,
      deploy_ssh_key: deployMethod === 'ssh' ? (sshKey || null) : null,
    },
  });

  const tabs: { key: Tab; label: string; show: boolean }[] = [
    { key: 'general', label: 'General', show: true },
    { key: 'branding', label: 'Branding', show: true },
    { key: 'front-page', label: 'Front Page', show: true },
    { key: 'seo', label: 'SEO', show: true },
    { key: 'files', label: 'Files', show: true },
    { key: 'deploy', label: 'Deploy', show: isAdminOrOwner },
    { key: 'custom-code', label: 'Custom Code', show: isAdminOrOwner },
    { key: 'global-styles', label: 'Global Styles', show: true },
    { key: 'languages', label: 'Languages', show: true },
    { key: 'forms', label: 'Forms', show: true },
    { key: 'ai', label: 'AI', show: isAdminOrOwner },
    { key: 'danger', label: 'Danger Zone', show: true },
  ];

  if (isLoading) {
    return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  }
  if (error) {
    return <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">Failed to load site settings.</div>;
  }

  const currentHomepage = sitePages?.find(p => p.id === homepageId);

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
            <button key={tab.key} onClick={() => setActiveTab(tab.key)}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                activeTab === tab.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}>{tab.label}</button>
          ))}
        </nav>
      </div>

      {updateMutation.isSuccess && (
        <div className="mb-6 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">Settings saved successfully.</div>
      )}
      {updateMutation.isError && (
        <div className="mb-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">Failed to save settings.</div>
      )}

      {/* General */}
      {activeTab === 'general' && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
              <input type="text" value={name} onChange={(e) => setName(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Slug</label>
              <input type="text" value={site?.slug ?? ''} readOnly
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-500 cursor-not-allowed" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select value={status} onChange={(e) => setStatus(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="draft">Draft</option>
              </select>
            </div>
            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
              <div>
                <p className="text-sm font-medium text-gray-900">Auto-publish</p>
                <p className="text-xs text-gray-500 mt-0.5">Automatically rebuild and deploy the site when content changes (pages, posts, menus, settings)</p>
              </div>
              <button
                onClick={() => setAutoPublish(!autoPublish)}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${autoPublish ? 'bg-blue-600' : 'bg-gray-300'}`}
              >
                <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm ${autoPublish ? 'translate-x-6' : 'translate-x-1'}`} />
              </button>
            </div>
            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveGeneral} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Branding */}
      {activeTab === 'branding' && (
        <div className="max-w-2xl space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <h3 className="font-semibold text-gray-900 text-sm">Logo & Identity</h3>

            <div>
              <label className="text-[11px] text-gray-500 mb-1 block">Logo Image</label>
              <div className="flex items-center gap-3">
                {logoUrl && <img src={logoUrl} alt="Logo" className="h-10 max-w-[160px] object-contain rounded border" />}
                <input type="text" value={logoUrl} onChange={e => setLogoUrl(e.target.value)}
                  className="input input-bordered input-sm flex-1 text-[12px]" placeholder="Logo URL or upload path" />
              </div>
              <p className="text-[10px] text-gray-400 mt-1">Upload via File Manager, then paste the URL here</p>
            </div>

            <div>
              <label className="text-[11px] text-gray-500 mb-1 block">Tagline</label>
              <input type="text" value={tagline} onChange={e => setTagline(e.target.value)}
                className="input input-bordered input-sm w-full text-[12px]" placeholder="A short description of your site" />
            </div>
          </div>

          <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <h3 className="font-semibold text-gray-900 text-sm">Social Links</h3>
            <p className="text-[10px] text-gray-400">Add your social media profile URLs. Leave empty to hide.</p>
            {(['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'github', 'tiktok', 'telegram', 'email'] as const).map(platform => (
              <div key={platform}>
                <label className="text-[11px] text-gray-500 mb-1 block capitalize">{platform === 'email' ? 'Email Address' : platform}</label>
                <input type={platform === 'email' ? 'email' : 'url'}
                  value={socialLinks[platform] || ''}
                  onChange={e => setSocialLinks(prev => ({ ...prev, [platform]: e.target.value }))}
                  className="input input-bordered input-sm w-full text-[12px]"
                  placeholder={platform === 'email' ? 'contact@example.com' : `https://${platform}.com/...`} />
              </div>
            ))}
          </div>

          <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
            <h3 className="font-semibold text-gray-900 text-sm">Footer</h3>

            <div>
              <label className="text-[11px] text-gray-500 mb-1 block">Footer Text</label>
              <textarea value={footerText} onChange={e => setFooterText(e.target.value)} rows={2}
                className="textarea textarea-bordered textarea-sm w-full text-[12px]"
                placeholder="Custom text shown in the footer" />
            </div>

            <div>
              <label className="text-[11px] text-gray-500 mb-1 block">Copyright Notice</label>
              <input type="text" value={footerCopyright} onChange={e => setFooterCopyright(e.target.value)}
                className="input input-bordered input-sm w-full text-[12px]"
                placeholder={`© ${new Date().getFullYear()} Your Site Name`} />
            </div>
          </div>

          <div className="flex justify-end">
            <button onClick={saveBranding} disabled={updateMutation.isPending}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {updateMutation.isPending ? 'Saving...' : 'Save Branding'}
            </button>
          </div>
        </div>
      )}

      {/* Front Page */}
      {activeTab === 'front-page' && (
        <div className="max-w-2xl space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            <div className="flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-lg">
              <Home className="h-5 w-5 text-blue-600 mt-0.5 shrink-0" />
              <div>
                <p className="text-sm font-medium text-blue-800">Front page</p>
                <p className="text-sm text-blue-600 mt-0.5">Choose what visitors see when they open your site's root URL (/).</p>
              </div>
            </div>

            {/* Homepage type selector */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Homepage type</label>
              <div className="space-y-2">
                {([
                  { value: 'page', label: 'Page', desc: 'A standard page with blocks. Simple and straightforward.' },
                  { value: 'grid', label: 'Grid layout', desc: 'A grid with zones — header, sidebar, widgets, content area. For complex layouts.' },
                  { value: 'blog', label: 'Blog feed', desc: 'Shows latest blog posts as the homepage. Like a classic blog.' },
                ] as const).map(opt => (
                  <label key={opt.value}
                    className={`flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors ${
                      homepageType === opt.value ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200'
                    }`}>
                    <input type="radio" name="homepage_type" value={opt.value}
                      checked={homepageType === opt.value}
                      onChange={() => setHomepageType(opt.value)}
                      className="mt-0.5 text-blue-600" />
                    <div>
                      <p className="text-sm font-medium text-gray-800">{opt.label}</p>
                      <p className="text-xs text-gray-500 mt-0.5">{opt.desc}</p>
                    </div>
                  </label>
                ))}
              </div>
            </div>

            {/* Page selector — shown when type is 'page' */}
            {homepageType === 'page' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Select page</label>
                <select value={homepageId} onChange={(e) => setHomepageId(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">— Auto-detect (page with slug "home") —</option>
                  {sitePages?.map(p => (
                    <option key={p.id} value={p.id}>
                      {p.title} (/{p.slug}) {p.status !== 'published' ? `[${p.status}]` : ''}
                    </option>
                  ))}
                </select>
                {homepageId && !sitePages?.find(p => p.id === homepageId) && (
                  <p className="mt-2 text-xs text-red-600">The selected page no longer exists. Please choose another one.</p>
                )}
                {currentHomepage && (
                  <p className="mt-2 text-xs text-green-600">Currently: "{currentHomepage.title}" will be published at /</p>
                )}
              </div>
            )}

            {/* Grid selector — shown when type is 'grid' */}
            {homepageType === 'grid' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Select grid</label>
                <select value={homepageGridId} onChange={(e) => setHomepageGridId(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">— Select a grid —</option>
                  {siteGrids?.map(g => (
                    <option key={g.id} value={g.id}>{g.name}</option>
                  ))}
                </select>
                <p className="mt-1 text-xs text-gray-400">The grid's widget zones (latest posts, categories, etc.) will render as the homepage.</p>
                {homepageGridId && (
                  <a href={`/admin/sites/${siteId}/grids/${homepageGridId}/edit`}
                    className="mt-2 inline-flex items-center gap-1.5 text-xs text-purple-600 hover:underline">
                    <LayoutGrid className="h-3.5 w-3.5" /> Edit this grid
                  </a>
                )}
              </div>
            )}

            {/* Blog info — shown when type is 'blog' */}
            {homepageType === 'blog' && (
              <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                <p className="text-sm text-gray-600">Your homepage will show the latest blog posts, just like /blog. No additional configuration needed.</p>
              </div>
            )}

            {/* Blog page */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Blog page URL</label>
              <select value={blogPageId} onChange={(e) => setBlogPageId(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— Default (/blog) —</option>
                {sitePages?.map(p => (
                  <option key={p.id} value={p.id}>{p.title} (/{p.slug})</option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-400">Optional. Select a page to use as your blog index.</p>
            </div>

            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveFrontPage} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save
              </button>
            </div>
          </div>
        </div>
      )}

      {/* SEO */}
      {activeTab === 'seo' && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Title Template</label>
              <input type="text" value={seoTitleTemplate} onChange={(e) => setSeoTitleTemplate(e.target.value)}
                placeholder="%page_title% | %site_name%"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Default Meta Description</label>
              <textarea value={seoDescription} onChange={(e) => setSeoDescription(e.target.value)} rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Default OG Image URL</label>
              <input type="url" value={ogImageUrl} onChange={(e) => setOgImageUrl(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveSeo} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Files */}
      {activeTab === 'files' && (
        <div className="max-w-2xl space-y-6">
          <div>
            <h3 className="text-lg font-semibold text-gray-900 mb-1">Allowed File Extensions</h3>
            <p className="text-sm text-gray-500 mb-4">Manage which file types can be uploaded to the media library.</p>
          </div>

          {/* Add new extension */}
          <div className="flex gap-2">
            <input
              type="text"
              value={newExt}
              onChange={(e) => setNewExt(e.target.value.toLowerCase().replace(/[^a-z0-9]/g, ''))}
              placeholder="Add extension (e.g. csv)"
              className="input input-bordered input-sm flex-1"
              onKeyDown={(e) => {
                if (e.key === 'Enter' && newExt && !allowedExtensions.includes(newExt)) {
                  setAllowedExtensions([...allowedExtensions, newExt]);
                  setNewExt('');
                }
              }}
            />
            <button
              onClick={() => {
                if (newExt && !allowedExtensions.includes(newExt)) {
                  setAllowedExtensions([...allowedExtensions, newExt]);
                  setNewExt('');
                }
              }}
              className="btn btn-sm btn-primary"
              disabled={!newExt || allowedExtensions.includes(newExt)}
            >
              Add
            </button>
          </div>

          {/* Extension list */}
          <div className="flex flex-wrap gap-2">
            {allowedExtensions.map((ext) => (
              <span
                key={ext}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 rounded-lg text-sm font-medium text-gray-700 group hover:bg-red-50 hover:text-red-700 transition-colors"
              >
                .{ext}
                <button
                  onClick={() => setAllowedExtensions(allowedExtensions.filter(e => e !== ext))}
                  className="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-600 transition-opacity"
                  title={`Remove .${ext}`}
                >
                  &times;
                </button>
              </span>
            ))}
          </div>

          {allowedExtensions.length === 0 && (
            <p className="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-3">
              No extensions allowed — all uploads will be blocked.
            </p>
          )}

          {/* Quick add presets */}
          <div className="border-t border-gray-200 pt-4">
            <p className="text-xs text-gray-400 mb-2 uppercase tracking-wider font-medium">Quick Add</p>
            <div className="flex flex-wrap gap-2">
              {[
                { label: 'Images', exts: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'] },
                { label: 'Documents', exts: ['pdf', 'doc', 'docx', 'txt', 'md', 'csv', 'xls', 'xlsx', 'pptx'] },
                { label: 'Video', exts: ['mp4', 'mov', 'mpg', 'avi', 'webm'] },
                { label: 'Audio', exts: ['mp3', 'wav', 'ogg', 'flac'] },
                { label: 'Archives', exts: ['zip', 'rar', 'tar', 'gz'] },
              ].map(({ label, exts }) => (
                <button
                  key={label}
                  onClick={() => {
                    const merged = [...new Set([...allowedExtensions, ...exts])];
                    setAllowedExtensions(merged);
                  }}
                  className="px-2.5 py-1 text-xs border border-gray-200 rounded-md text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors"
                >
                  + {label}
                </button>
              ))}
            </div>
          </div>

          <button onClick={saveFiles} disabled={updateMutation.isPending}
            className="btn btn-primary btn-sm gap-1.5">
            <Save size={14} /> Save
          </button>
        </div>
      )}

      {/* Deploy */}
      {activeTab === 'deploy' && isAdminOrOwner && (
        <div className="max-w-2xl space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Deploy method</label>
              <div className="space-y-2">
                {([
                  { value: 'local', label: 'Local', desc: 'Copy files to a directory on this server. For sites hosted on the same machine.' },
                  { value: 'ssh', label: 'SSH (rsync)', desc: 'Sync files to a remote server via SSH. Secure, fast, sends only changes.' },
                  { value: 'zip_only', label: 'ZIP download only', desc: 'No auto-deploy. Download a ZIP file with the full site and upload it yourself.' },
                ] as const).map(opt => (
                  <label key={opt.value}
                    className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${deployMethod === opt.value ? 'border-blue-300 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'}`}>
                    <input type="radio" name="deploy_method" value={opt.value} checked={deployMethod === opt.value}
                      onChange={() => setDeployMethod(opt.value)} className="radio radio-sm radio-primary mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-gray-900">{opt.label}</p>
                      <p className="text-xs text-gray-500">{opt.desc}</p>
                    </div>
                  </label>
                ))}
              </div>
            </div>

            {deployMethod === 'ssh' && (
              <div className="space-y-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h3 className="text-sm font-medium text-gray-800">SSH connection</h3>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs text-gray-500 mb-1 block">Host</label>
                    <input type="text" className="input input-bordered input-sm w-full" placeholder="example.com"
                      value={sshHost} onChange={(e) => setSshHost(e.target.value)} />
                  </div>
                  <div>
                    <label className="text-xs text-gray-500 mb-1 block">Port</label>
                    <input type="number" className="input input-bordered input-sm w-full" placeholder="22"
                      value={sshPort} onChange={(e) => setSshPort(Number(e.target.value))} />
                  </div>
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">User</label>
                  <input type="text" className="input input-bordered input-sm w-full" placeholder="deploy"
                    value={sshUser} onChange={(e) => setSshUser(e.target.value)} />
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Remote path</label>
                  <input type="text" className="input input-bordered input-sm w-full" placeholder="/var/www/mysite/public_html/"
                    value={sshPath} onChange={(e) => setSshPath(e.target.value)} />
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">SSH key path (on this server)</label>
                  <input type="text" className="input input-bordered input-sm w-full" placeholder="/root/.ssh/id_ed25519"
                    value={sshKey} onChange={(e) => setSshKey(e.target.value)} />
                  <p className="text-[10px] text-gray-400 mt-1">Path to the private key file on the CMS server. Leave empty to use the default SSH key.</p>
                </div>
              </div>
            )}

            <div className="pt-4 border-t border-gray-100 flex items-center gap-3">
              <button onClick={saveDeploy} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Deploy Settings
              </button>
            </div>
          </div>

          {/* ZIP Download — always available */}
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h3 className="text-sm font-medium text-gray-800 mb-2">Download site as ZIP</h3>
            <p className="text-xs text-gray-500 mb-4">Download the latest published build as a ZIP file. Contains all HTML pages, assets, sitemap, and RSS feed.</p>
            <a href={`/api/v1/sites/${siteId}/download-zip`}
              className="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900">
              Download ZIP
            </a>
          </div>
        </div>
      )}

      {/* Custom Code */}
      {activeTab === 'custom-code' && isAdminOrOwner && (
        <div className="max-w-2xl">
          <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
            <AlertTriangle className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-amber-800">Be careful with custom code</p>
              <p className="text-sm text-amber-700 mt-1">Only add code from trusted sources.</p>
            </div>
          </div>
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                Google Analytics
              </label>
              <input value={gaId} onChange={(e) => setGaId(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="G-XXXXXXXXXX or UA-XXXXXXXX-X" />
              <p className="text-[10px] text-gray-400 mt-1">Enter your Google Analytics Measurement ID. The tracking script is automatically added to all published pages.</p>
            </div>
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1"><Code className="h-4 w-4" />Head Scripts</label>
              <textarea value={headScripts} onChange={(e) => setHeadScripts(e.target.value)} rows={6}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y" />
            </div>
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1"><Code className="h-4 w-4" />Body Scripts</label>
              <textarea value={bodyScripts} onChange={(e) => setBodyScripts(e.target.value)} rows={6}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y" />
            </div>
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1"><Code className="h-4 w-4" />Custom CSS</label>
              <textarea value={customCss} onChange={(e) => setCustomCss(e.target.value)} rows={8}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y" />
            </div>
            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveCustomCode} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Global Styles */}
      {activeTab === 'global-styles' && (
        <div className="max-w-2xl">
          <div className="space-y-6 bg-white border border-gray-200 rounded-xl p-6">
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-1">Typography</h3>
              <p className="text-xs text-gray-400 mb-3">Default font settings applied to all pages.</p>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Font Family</label>
                  <select value={globalFontFamily} onChange={e => setGlobalFontFamily(e.target.value)}
                    className="select select-bordered select-sm w-full text-xs">
                    <option value="">Default (inherit)</option>
                    {['Inter', 'Georgia', 'Arial', 'Helvetica', 'Times New Roman', 'Verdana', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Playfair Display', 'Merriweather', 'Poppins', 'Raleway'].map(f =>
                      <option key={f} value={f}>{f}</option>
                    )}
                  </select>
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Base Font Size</label>
                  <input value={globalFontSize} onChange={e => setGlobalFontSize(e.target.value)}
                    className="input input-bordered input-sm w-full text-xs" placeholder="16px" />
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Line Height</label>
                  <input value={globalLineHeight} onChange={e => setGlobalLineHeight(e.target.value)}
                    className="input input-bordered input-sm w-full text-xs" placeholder="1.6" />
                </div>
              </div>
            </div>

            <div className="border-t border-gray-100 pt-4">
              <h3 className="text-sm font-semibold text-gray-700 mb-1">Colors</h3>
              <p className="text-xs text-gray-400 mb-3">Default colors for text, background, and links.</p>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Text Color</label>
                  <div className="flex gap-1">
                    <input type="color" value={globalTextColor || '#1e293b'} onChange={e => setGlobalTextColor(e.target.value)}
                      className="w-8 h-8 rounded cursor-pointer border border-gray-200" />
                    <input value={globalTextColor} onChange={e => setGlobalTextColor(e.target.value)}
                      className="input input-bordered input-xs flex-1 font-mono text-[10px]" placeholder="#1e293b" />
                  </div>
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Background</label>
                  <div className="flex gap-1">
                    <input type="color" value={globalBgColor || '#ffffff'} onChange={e => setGlobalBgColor(e.target.value)}
                      className="w-8 h-8 rounded cursor-pointer border border-gray-200" />
                    <input value={globalBgColor} onChange={e => setGlobalBgColor(e.target.value)}
                      className="input input-bordered input-xs flex-1 font-mono text-[10px]" placeholder="#ffffff" />
                  </div>
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Link Color</label>
                  <div className="flex gap-1">
                    <input type="color" value={globalLinkColor || '#3b82f6'} onChange={e => setGlobalLinkColor(e.target.value)}
                      className="w-8 h-8 rounded cursor-pointer border border-gray-200" />
                    <input value={globalLinkColor} onChange={e => setGlobalLinkColor(e.target.value)}
                      className="input input-bordered input-xs flex-1 font-mono text-[10px]" placeholder="#3b82f6" />
                  </div>
                </div>
              </div>
            </div>

            <div className="border-t border-gray-100 pt-4">
              <h3 className="text-sm font-semibold text-gray-700 mb-1">Container</h3>
              <p className="text-xs text-gray-400 mb-3">Default content width and padding.</p>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Max Width</label>
                  <input value={globalContainerWidth} onChange={e => setGlobalContainerWidth(e.target.value)}
                    className="input input-bordered input-sm w-full text-xs" placeholder="1200px" />
                </div>
                <div>
                  <label className="text-xs text-gray-500 mb-1 block">Horizontal Padding</label>
                  <input value={globalContainerPadding} onChange={e => setGlobalContainerPadding(e.target.value)}
                    className="input input-bordered input-sm w-full text-xs" placeholder="24px" />
                </div>
              </div>
            </div>

            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveGlobalStyles} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Global Styles
              </button>
              <p className="text-[10px] text-gray-400 mt-2">These styles are applied as CSS variables to all published pages. Re-publish to see changes.</p>
            </div>
          </div>
        </div>
      )}

      {/* Languages */}
      {activeTab === 'languages' && (
        <div className="max-w-2xl">
          <div className="space-y-6 bg-white border border-gray-200 rounded-xl p-6">
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-1">Site Languages</h3>
              <p className="text-xs text-gray-400 mb-3">Configure which languages your site supports. Pages can be translated into any enabled language.</p>
            </div>

            <div>
              <label className="text-xs text-gray-500 mb-1 block">Default Language</label>
              <select value={defaultLanguage} onChange={e => setDefaultLanguage(e.target.value)}
                className="select select-bordered select-sm w-full text-xs">
                {[
                  { code: 'en', label: 'English' }, { code: 'bg', label: 'Български' },
                  { code: 'de', label: 'Deutsch' }, { code: 'fr', label: 'Français' },
                  { code: 'es', label: 'Español' }, { code: 'it', label: 'Italiano' },
                  { code: 'nl', label: 'Nederlands' }, { code: 'pt', label: 'Português' },
                  { code: 'ru', label: 'Русский' }, { code: 'ja', label: '日本語' },
                  { code: 'zh', label: '中文' }, { code: 'ko', label: '한국어' },
                  { code: 'ar', label: 'العربية' }, { code: 'tr', label: 'Türkçe' },
                  { code: 'pl', label: 'Polski' }, { code: 'cs', label: 'Čeština' },
                  { code: 'ro', label: 'Română' }, { code: 'uk', label: 'Українська' },
                  { code: 'el', label: 'Ελληνικά' }, { code: 'sv', label: 'Svenska' },
                ].map(l => <option key={l.code} value={l.code}>{l.label} ({l.code})</option>)}
              </select>
            </div>

            <div>
              <label className="text-xs text-gray-500 mb-2 block">Additional Languages</label>
              <div className="flex flex-wrap gap-2">
                {[
                  { code: 'en', label: 'EN' }, { code: 'bg', label: 'БГ' },
                  { code: 'de', label: 'DE' }, { code: 'fr', label: 'FR' },
                  { code: 'es', label: 'ES' }, { code: 'it', label: 'IT' },
                  { code: 'nl', label: 'NL' }, { code: 'pt', label: 'PT' },
                  { code: 'ru', label: 'RU' }, { code: 'ja', label: 'JA' },
                  { code: 'zh', label: 'ZH' }, { code: 'ko', label: 'KO' },
                  { code: 'ar', label: 'AR' }, { code: 'tr', label: 'TR' },
                  { code: 'pl', label: 'PL' }, { code: 'cs', label: 'CS' },
                  { code: 'ro', label: 'RO' }, { code: 'uk', label: 'UK' },
                  { code: 'el', label: 'EL' }, { code: 'sv', label: 'SV' },
                ].filter(l => l.code !== defaultLanguage).map(l => {
                  const active = siteLanguages.includes(l.code);
                  return (
                    <button key={l.code}
                      onClick={() => setSiteLanguages(active ? siteLanguages.filter(c => c !== l.code) : [...siteLanguages, l.code])}
                      className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                        active ? 'bg-blue-50 border-blue-300 text-blue-700' : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-gray-300'
                      }`}>
                      {l.label}
                    </button>
                  );
                })}
              </div>
              <p className="text-[10px] text-gray-400 mt-2">Click to enable/disable languages. Enabled languages appear in the page editor for content translation.</p>
            </div>

            {siteLanguages.length > 0 && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs text-blue-700 font-medium mb-1">Enabled: {defaultLanguage.toUpperCase()} (default) + {siteLanguages.map(c => c.toUpperCase()).join(', ')}</p>
                <p className="text-[10px] text-blue-500">To translate a page: open the page editor → Page tab → set the language and link to the original page.</p>
              </div>
            )}

            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveLanguages} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save Languages
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Forms */}
      {activeTab === 'forms' && (
        <FormSubmissionsPanel siteId={siteId} />
      )}

      {/* AI */}
      {activeTab === 'ai' && isAdminOrOwner && (
        <div className="max-w-2xl">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-1">AI configuration</h3>
              <p className="text-sm text-gray-500">API keys for AI-powered features: Issue Composer, content generation, SEO suggestions, alt text generation.</p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Anthropic API key (Claude)</label>
              <input type="password" value={anthropicKey} onChange={(e) => setAnthropicKey(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="sk-ant-api03-..." />
              <p className="mt-1 text-xs text-gray-400">
                Used by Issue Composer (curation + layout), AI content generation, and SEO suggestions.
                Get your key at <a href="https://console.anthropic.com/" target="_blank" rel="noopener" className="text-blue-600 hover:underline">console.anthropic.com</a>
              </p>
              {anthropicKey && <p className="mt-1 text-xs text-green-600">Key set ({anthropicKey.slice(0, 10)}...)</p>}
              {!anthropicKey && <p className="mt-1 text-xs text-amber-500">No key set — AI features will use fallback algorithms</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">OpenAI API key (optional)</label>
              <input type="password" value={openaiKey} onChange={(e) => setOpenaiKey(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="sk-..." />
              <p className="mt-1 text-xs text-gray-400">Optional fallback for content generation if Claude is unavailable.</p>
            </div>

            <div className="pt-4 border-t border-gray-100">
              <button onClick={saveAi} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save API keys
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Magazine Viewer */}
      {activeTab === 'magazine' && (
        <div className="space-y-6">
          <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-1">Magazine Viewer</h3>
            <p className="text-sm text-gray-500 mb-6">Global settings for all magazine and issue flipbook viewers.</p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Page transition</label>
                <select value={magTransition} onChange={e => setMagTransition(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                  <option value="turn">Page turn (realistic 3D)</option>
                  <option value="curl">Page curl</option>
                  <option value="flip">Flip (simple)</option>
                  <option value="slide">Slide</option>
                  <option value="fade">Fade</option>
                  <option value="none">None (instant)</option>
                </select>
                <p className="mt-1 text-xs text-gray-400">Animation when navigating between pages</p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Spread mode</label>
                <select value={magSpread} onChange={e => setMagSpread(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                  <option value="spread">Spread (2 pages side by side)</option>
                  <option value="single">Single page</option>
                  <option value="auto">Auto (adapts to screen size)</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Background color</label>
                <select value={magBg} onChange={e => setMagBg(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                  <option value="#0a0a0a">Dark</option>
                  <option value="#1a1a2e">Navy</option>
                  <option value="#2d2d2d">Charcoal</option>
                  <option value="#f5f3ef">Warm light</option>
                  <option value="#f0f0f0">Light grey</option>
                  <option value="#ffffff">White</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Transition speed: {magSpeed}ms</label>
                <input type="range" min={200} max={1500} step={50} value={magSpeed}
                  onChange={e => setMagSpeed(Number(e.target.value))}
                  className="w-full h-2 bg-gray-200 rounded-lg cursor-pointer accent-blue-600" />
                <div className="flex justify-between text-xs text-gray-400 mt-1">
                  <span>Fast (200ms)</span><span>Slow (1500ms)</span>
                </div>
              </div>
            </div>

            <div className="border-t border-gray-100 mt-6 pt-6">
              <h4 className="text-sm font-semibold text-gray-700 mb-4">Page Numbers</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="flex items-center gap-3">
                  <input type="checkbox" checked={magPageNumbers} onChange={e => setMagPageNumbers(e.target.checked)}
                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                  <label className="text-sm text-gray-700">Show page numbers</label>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Position</label>
                  <select value={magPnPosition} onChange={e => setMagPnPosition(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="bottom">Bottom</option>
                    <option value="top">Top</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Alignment</label>
                  <select value={magPnAlign} onChange={e => setMagPnAlign(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="outer">Outer (mirrored — left page left, right page right)</option>
                    <option value="center">Center</option>
                    <option value="left">Always left</option>
                    <option value="right">Always right</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Font size</label>
                  <select value={magPnSize} onChange={e => setMagPnSize(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="7">7px</option>
                    <option value="8">8px</option>
                    <option value="9">9px (default)</option>
                    <option value="10">10px</option>
                    <option value="11">11px</option>
                    <option value="12">12px</option>
                    <option value="14">14px</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="pt-4 border-t border-gray-100 mt-6">
              <button onClick={saveMagazine} disabled={updateMutation.isPending}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                {updateMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Save magazine settings
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Danger Zone */}
      {activeTab === 'danger' && (
        <DangerZone siteId={siteId} siteName={site?.name ?? ''} onDelete={() => setShowDeleteConfirm(true)} />
      )}

      <ConfirmDialog
        open={showDeleteConfirm}
        title="Delete site"
        message={`Are you sure you want to delete "${site?.name}"? All content will be permanently deleted.`}
        confirmText="Delete permanently"
        variant="danger"
        onConfirm={() => deleteMutation.mutate()}
        onClose={() => setShowDeleteConfirm(false)}
      />
    </div>
  );
}

function DangerZone({ siteId, siteName, onDelete }: { siteId: string; siteName: string; onDelete: () => void }) {
  const queryClient = useQueryClient();
  const [resetConfirm, setResetConfirm] = useState('');
  const [factoryConfirm, setFactoryConfirm] = useState('');
  const [resetOptions, setResetOptions] = useState({
    pages: true, posts: true, categories: true, tags: true,
    assets: true, menus: false, deployments: true,
  });

  const previewQuery = useQuery<Record<string, number>>({
    queryKey: ['reset-preview', siteId],
    queryFn: () => api.get(`/sites/${siteId}/reset/preview`).then(r => r.data.data),
  });

  const resetMutation = useMutation({
    mutationFn: () => api.post(`/sites/${siteId}/reset/content`, {
      confirm: resetConfirm,
      options: resetOptions,
    }),
    onSuccess: (res) => {
      queryClient.invalidateQueries();
      setResetConfirm('');
      alert('Content reset complete: ' + JSON.stringify(res.data.data.deleted));
    },
    onError: (err: any) => {
      alert(err.response?.data?.message || 'Reset failed');
    },
  });

  const factoryMutation = useMutation({
    mutationFn: () => api.post(`/sites/${siteId}/reset/factory`, { confirm: factoryConfirm }),
    onSuccess: () => {
      queryClient.invalidateQueries();
      setFactoryConfirm('');
      alert('Factory reset complete. Site is now empty.');
    },
    onError: (err: any) => {
      alert(err.response?.data?.message || 'Factory reset failed');
    },
  });

  const counts = previewQuery.data;

  return (
    <div className="max-w-2xl space-y-6">
      {/* Content Reset */}
      <div className="bg-white rounded-xl border border-orange-200 shadow-sm p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-1">Reset Content</h3>
        <p className="text-sm text-gray-500 mb-4">
          Selectively wipe content to start fresh. Useful before re-importing. Your site settings, theme, and grids are preserved.
        </p>

        {counts && (
          <div className="grid grid-cols-4 gap-3 mb-4 text-center">
            {Object.entries(counts).filter(([k]) => k !== 'blocks' && k !== 'deployments').map(([k, v]) => (
              <div key={k} className="bg-gray-50 rounded-lg p-2">
                <p className="text-lg font-bold text-gray-900">{v as number}</p>
                <p className="text-xs text-gray-500 capitalize">{k}</p>
              </div>
            ))}
          </div>
        )}

        <div className="space-y-2 mb-4">
          {Object.entries(resetOptions).map(([key, checked]) => (
            <label key={key} className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={checked}
                onChange={e => setResetOptions({ ...resetOptions, [key]: e.target.checked })}
                className="rounded border-gray-300 text-orange-600" />
              <span className="capitalize">{key}</span>
              {counts && <span className="text-gray-400 ml-auto">{(counts as any)[key] ?? 0}</span>}
            </label>
          ))}
        </div>

        <div className="border-t border-gray-100 pt-4">
          <p className="text-xs text-gray-500 mb-2">Type <strong>{siteName}</strong> to confirm:</p>
          <div className="flex gap-2">
            <input type="text" value={resetConfirm} onChange={e => setResetConfirm(e.target.value)}
              placeholder={siteName}
              className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" />
            <button onClick={() => resetMutation.mutate()}
              disabled={resetConfirm !== siteName || resetMutation.isPending}
              className="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 disabled:opacity-50">
              {resetMutation.isPending ? 'Resetting...' : 'Reset Content'}
            </button>
          </div>
        </div>
      </div>

      {/* Factory Reset */}
      <div className="bg-white rounded-xl border border-red-300 shadow-sm p-6">
        <h3 className="text-lg font-semibold text-red-700 mb-1">Factory Reset</h3>
        <p className="text-sm text-gray-500 mb-4">
          Wipe <strong>everything</strong> — all pages, posts, categories, tags, assets, menus, and deployments. Only the site, theme, user account, and grid presets survive. This cannot be undone.
        </p>

        <div className="border-t border-gray-100 pt-4">
          <p className="text-xs text-gray-500 mb-2">Type <strong>FACTORY RESET {siteName}</strong> to confirm:</p>
          <div className="flex gap-2">
            <input type="text" value={factoryConfirm} onChange={e => setFactoryConfirm(e.target.value)}
              placeholder={`FACTORY RESET ${siteName}`}
              className="flex-1 px-3 py-2 border border-red-200 rounded-lg text-sm font-mono" />
            <button onClick={() => factoryMutation.mutate()}
              disabled={factoryConfirm !== `FACTORY RESET ${siteName}` || factoryMutation.isPending}
              className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50">
              {factoryMutation.isPending ? 'Resetting...' : 'Factory Reset'}
            </button>
          </div>
        </div>
      </div>

      {/* Delete Site */}
      <div className="bg-white rounded-xl border border-red-200 shadow-sm p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Delete Site</h3>
        <p className="text-sm text-gray-500 mb-4">Permanently delete the entire site. This removes everything including the site record.</p>
        <button onClick={onDelete}
          className="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
          Delete this site
        </button>
      </div>
    </div>
  );
}

function FormSubmissionsPanel({ siteId }: { siteId: string }) {
  const { data: submissions, isLoading, refetch } = useQuery({
    queryKey: ['form-submissions', siteId],
    queryFn: () => api.get(`/sites/${siteId}/form-submissions`).then(r => r.data.data as Array<{ id: string; data: Record<string, string>; submitted_at: string; ip: string }>),
  });

  const deleteMut = useMutation({
    mutationFn: (index: number) => api.delete(`/sites/${siteId}/form-submissions/${index}`),
    onSuccess: () => refetch(),
  });

  if (isLoading) return <div className="text-sm text-gray-400 p-4">Loading submissions...</div>;

  return (
    <div className="max-w-3xl">
      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-semibold text-gray-700">Form Submissions</h3>
          <span className="text-xs text-gray-400">{submissions?.length || 0} submissions</span>
        </div>

        {!submissions || submissions.length === 0 ? (
          <div className="text-center py-8 text-gray-400 text-sm">No form submissions yet.</div>
        ) : (
          <div className="space-y-3">
            {submissions.map((sub, idx) => (
              <div key={sub.id} className="border border-gray-100 rounded-lg p-3">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-[10px] text-gray-400">{new Date(sub.submitted_at).toLocaleString()}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-[9px] text-gray-300">{sub.ip}</span>
                    <button onClick={() => deleteMut.mutate(idx)} className="text-[10px] text-red-400 hover:text-red-600">Delete</button>
                  </div>
                </div>
                <div className="space-y-1">
                  {Object.entries(sub.data).map(([key, val]) => (
                    <div key={key} className="flex gap-2 text-xs">
                      <span className="text-gray-500 font-medium w-24 shrink-0">{key}:</span>
                      <span className="text-gray-700">{String(val)}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
