import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Globe, FileText, Newspaper, ExternalLink, Download, ArrowRight, X, Palette, Layout, LayoutGrid, Check, Eye } from 'lucide-react';
import { sites, api } from '@/lib/api';
import { StatusBadge } from '@/components/ui/StatusBadge';

interface Site {
  id: string;
  name: string;
  slug: string;
  custom_domain?: string;
  status: string;
  pages_count?: number;
  posts_count?: number;
}

export default function Dashboard() {
  const navigate = useNavigate();
  const [wizardOpen, setWizardOpen] = useState(false);
  const { data, isLoading, error } = useQuery<Site[]>({
    queryKey: ['sites'],
    queryFn: () => sites.list().then(r => r.data.data),
  });

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-lg font-medium text-base-content/90">Dashboard</h1>
        <p className="mt-0.5 text-[13px] text-base-content/40">Manage your sites</p>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <span className="loading loading-spinner loading-sm text-base-content/20"></span>
        </div>
      )}

      {error && (
        <div className="alert alert-error text-[13px]">Failed to load sites. Please try again.</div>
      )}

      {data && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.map((site) => (
            <div key={site.id} onClick={() => navigate(`/sites/${site.id}/pages`)}
              className="card bg-base-100 border border-base-300/40 hover:border-base-300/70 cursor-pointer transition-all">
              <div className="card-body p-5 gap-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                      <Globe className="h-4 w-4 text-primary" strokeWidth={1.5} />
                    </div>
                    <div>
                      <h3 className="text-sm font-medium text-base-content/90">{site.name}</h3>
                      <p className="text-[11px] text-base-content/30">/{site.slug}</p>
                    </div>
                  </div>
                  <StatusBadge status={site.status} />
                </div>

                <div className="flex items-center gap-5 text-[12px] text-base-content/40">
                  <span className="flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5" strokeWidth={1.5} />
                    {site.pages_count ?? 0} pages
                  </span>
                  <span className="flex items-center gap-1.5">
                    <Newspaper className="h-3.5 w-3.5" strokeWidth={1.5} />
                    {site.posts_count ?? 0} posts
                  </span>
                </div>

                <div className="flex items-center gap-3 pt-3 border-t border-base-300/20">
                  <button onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/pages`); }}
                    className="text-[12px] text-primary hover:text-primary/80 font-medium">Pages</button>
                  <button onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/posts`); }}
                    className="text-[12px] text-primary hover:text-primary/80 font-medium">Posts</button>
                  <button onClick={(e) => { e.stopPropagation(); navigate(`/sites/${site.id}/settings`); }}
                    className="text-[12px] text-primary hover:text-primary/80 font-medium">Settings</button>
                  <button onClick={async (e) => {
                      e.stopPropagation();
                      const name = prompt('New site name:', `${site.name} (copy)`);
                      if (!name) return;
                      try {
                        const r = await sites.clone(site.id, name);
                        navigate(`/sites/${r.data.data.id}/pages`);
                      } catch { alert('Clone failed'); }
                    }}
                    className="text-[12px] text-base-content/40 hover:text-primary font-medium">Clone</button>
                  <a href={`/api/v1/sites/${site.id}/download-zip`}
                    onClick={(e) => e.stopPropagation()}
                    className="flex items-center gap-1 text-[12px] text-base-content/40 hover:text-primary font-medium">
                    <Download className="h-3 w-3" strokeWidth={1.5} />
                    ZIP
                  </a>
                  <a href={site.custom_domain ? `https://${site.custom_domain}` : `/sites/${site.slug}`}
                    target="_blank" rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="ml-auto flex items-center gap-1 text-[12px] text-base-content/40 hover:text-primary font-medium">
                    <ExternalLink className="h-3 w-3" strokeWidth={1.5} />
                    View Site
                  </a>
                </div>
              </div>
            </div>
          ))}

          <div onClick={() => setWizardOpen(true)}
            className="card border-2 border-dashed border-base-300/40 hover:border-primary/40 cursor-pointer transition-all min-h-[180px] flex items-center justify-center group">
            <div className="card-body items-center justify-center text-center p-5">
              <Plus className="h-8 w-8 text-base-content/15 group-hover:text-primary/40 mb-2 transition-colors" strokeWidth={1.5} />
              <span className="text-[13px] font-medium text-base-content/40 group-hover:text-primary/60">Create new site</span>
            </div>
          </div>

          {wizardOpen && <SiteWizard onClose={() => setWizardOpen(false)} />}
        </div>
      )}
    </div>
  );
}

interface WizardTheme {
  id: string;
  name: string;
  slug: string;
  description: string;
  modes: string[];
}

const STEP_LABELS = ['Basics', 'Theme', 'Template', 'Confirm'];
const TEMPLATE_OPTIONS = [
  { id: 'blank', icon: Layout, label: 'Blank Site', desc: 'Start from scratch with an empty homepage' },
  { id: 'blog', icon: Newspaper, label: 'Blog', desc: 'Home, About, Contact + blog with latest posts' },
  { id: 'portfolio', icon: Palette, label: 'Portfolio', desc: 'Home, About, Work gallery + Contact' },
  { id: 'business', icon: Globe, label: 'Business', desc: 'Home, About, Services, Team + Contact' },
  { id: 'full', icon: LayoutGrid, label: 'Full Site', desc: 'Home, Landing, Catalog, Portfolio, Contact, Blog, About, Features' },
];

// Template preview data — mirrors StarterTemplateService page definitions
type BlockWire = { type: 'heading' | 'text' | 'button' | 'posts' | 'gallery' | 'form' | 'columns'; cols?: number };
interface PagePreview { title: string; slug: string; blocks: BlockWire[][] }
const TEMPLATE_PREVIEWS: Record<string, PagePreview[]> = {
  blank: [
    { title: 'Home', slug: 'home', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'button' }]] },
  ],
  blog: [
    { title: 'Home', slug: 'home', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'button' }]] },
    { title: 'About', slug: 'about', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'text' }]] },
    { title: 'Contact', slug: 'contact', blocks: [[{ type: 'heading' }, { type: 'text' }], [{ type: 'form' }]] },
    { title: 'Blog', slug: 'blog', blocks: [[{ type: 'heading' }, { type: 'posts', cols: 3 }]] },
  ],
  portfolio: [
    { title: 'Home', slug: 'home', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'button' }]] },
    { title: 'About', slug: 'about', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'text' }]] },
    { title: 'Work', slug: 'work', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'gallery', cols: 3 }]] },
    { title: 'Contact', slug: 'contact', blocks: [[{ type: 'heading' }, { type: 'text' }], [{ type: 'form' }]] },
  ],
  business: [
    { title: 'Home', slug: 'home', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'button' }]] },
    { title: 'About', slug: 'about', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'text' }]] },
    { title: 'Services', slug: 'services', blocks: [[{ type: 'heading' }, { type: 'text' }], [{ type: 'columns', cols: 3 }]] },
    { title: 'Team', slug: 'team', blocks: [[{ type: 'heading' }, { type: 'text' }]] },
    { title: 'Contact', slug: 'contact', blocks: [[{ type: 'heading' }, { type: 'text' }], [{ type: 'form' }]] },
  ],
  full: [
    { title: 'Home', slug: 'home', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'button' }]] },
    { title: 'Landing', slug: 'landing', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'button' }], [{ type: 'columns', cols: 3 }], [{ type: 'heading' }, { type: 'button' }]] },
    { title: 'Catalog', slug: 'catalog', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'columns', cols: 3 }]] },
    { title: 'Portfolio', slug: 'portfolio', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'gallery', cols: 3 }]] },
    { title: 'Contact', slug: 'contact', blocks: [[{ type: 'heading' }, { type: 'text' }], [{ type: 'form' }]] },
    { title: 'Blog', slug: 'blog', blocks: [[{ type: 'heading' }, { type: 'posts', cols: 3 }]] },
    { title: 'About', slug: 'about', blocks: [[{ type: 'heading' }, { type: 'text' }, { type: 'text' }]] },
    { title: 'Features', slug: 'features', blocks: [[{ type: 'heading' }, { type: 'text' }], [{ type: 'columns', cols: 3 }]] },
  ],
};

/** Mini wireframe block renderer */
function WireBlock({ block }: { block: BlockWire }) {
  const base = 'rounded';
  switch (block.type) {
    case 'heading':
      return <div className={`${base} bg-base-content/15 h-3 w-3/5`} />;
    case 'text':
      return <div className="space-y-0.5"><div className={`${base} bg-base-content/8 h-1.5 w-full`} /><div className={`${base} bg-base-content/8 h-1.5 w-4/5`} /></div>;
    case 'button':
      return <div className={`${base} bg-primary/30 h-3 w-16`} />;
    case 'posts':
      return <div className="grid gap-1" style={{ gridTemplateColumns: `repeat(${block.cols || 3}, 1fr)` }}>
        {Array.from({ length: block.cols || 3 }).map((_, i) => (
          <div key={i} className="space-y-0.5">
            <div className={`${base} bg-base-content/10 h-5`} />
            <div className={`${base} bg-base-content/6 h-1.5 w-4/5`} />
          </div>
        ))}
      </div>;
    case 'gallery':
      return <div className="grid gap-0.5" style={{ gridTemplateColumns: `repeat(${block.cols || 3}, 1fr)` }}>
        {Array.from({ length: (block.cols || 3) * 2 }).map((_, i) => (
          <div key={i} className={`${base} bg-base-content/10 h-4`} />
        ))}
      </div>;
    case 'form':
      return <div className="space-y-1">
        <div className={`${base} bg-base-300/50 h-2.5 w-full border border-base-300/30`} />
        <div className={`${base} bg-base-300/50 h-2.5 w-full border border-base-300/30`} />
        <div className={`${base} bg-base-300/50 h-5 w-full border border-base-300/30`} />
        <div className={`${base} bg-primary/30 h-2.5 w-20`} />
      </div>;
    case 'columns':
      return <div className="grid gap-1" style={{ gridTemplateColumns: `repeat(${block.cols || 3}, 1fr)` }}>
        {Array.from({ length: block.cols || 3 }).map((_, i) => (
          <div key={i} className="space-y-0.5 p-1 border border-base-300/20 rounded">
            <div className={`${base} bg-base-content/12 h-2 w-3/4`} />
            <div className={`${base} bg-base-content/6 h-1.5 w-full`} />
          </div>
        ))}
      </div>;
    default:
      return null;
  }
}

/** Mini page wireframe */
function PageWireframe({ page }: { page: PagePreview }) {
  const colCount = page.blocks.length;
  return (
    <div className="border border-base-300/30 rounded-lg overflow-hidden bg-base-100">
      <div className="flex items-center gap-1 px-2 py-1 bg-base-200/60 border-b border-base-300/20">
        <div className="flex gap-0.5">
          <div className="w-1.5 h-1.5 rounded-full bg-base-content/10" />
          <div className="w-1.5 h-1.5 rounded-full bg-base-content/10" />
          <div className="w-1.5 h-1.5 rounded-full bg-base-content/10" />
        </div>
        <span className="text-[8px] text-base-content/40 ml-1">/{page.slug}</span>
      </div>
      <div className={`p-2 gap-2 ${colCount > 1 ? 'grid' : 'space-y-1.5'}`}
        style={colCount > 1 ? { gridTemplateColumns: `repeat(${colCount}, 1fr)` } : undefined}>
        {page.blocks.map((col, ci) => (
          <div key={ci} className="space-y-1.5">
            {col.map((b, bi) => <WireBlock key={bi} block={b} />)}
          </div>
        ))}
      </div>
    </div>
  );
}

function SiteWizard({ onClose }: { onClose: () => void; onCreate?: (id: string) => void }) {
  const [step, setStep] = useState(1);
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [selectedThemeId, setSelectedThemeId] = useState<string | null>(null);
  const [template, setTemplate] = useState('blank');
  const [topic, setTopic] = useState('');
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');
  const [createdSiteId, setCreatedSiteId] = useState<string | null>(null);
  const [templateResult, setTemplateResult] = useState('');
  const navigate = useNavigate();

  // Load available themes
  const [themes, setThemes] = useState<WizardTheme[]>([]);
  const [themesLoading, setThemesLoading] = useState(false);
  useEffect(() => {
    setThemesLoading(true);
    api.get('/available-themes').then(r => {
      const list = r.data.data || [];
      setThemes(list);
      if (list.length > 0 && !selectedThemeId) setSelectedThemeId(list[0].id);
    }).catch(() => {}).finally(() => setThemesLoading(false));
  }, []);

  const autoSlug = (n: string) => n.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const selectedTheme = themes.find(t => t.id === selectedThemeId);

  const handleCreate = async () => {
    if (!name.trim()) return;
    setCreating(true);
    setError('');
    try {
      const r = await sites.create({ name: name.trim(), slug: slug || autoSlug(name) });
      const siteId = r.data.data.id;
      setCreatedSiteId(siteId);

      // Assign selected theme
      if (selectedThemeId) {
        try {
          await api.post(`/sites/${siteId}/theme-engine/assign`, { theme_id: selectedThemeId });
        } catch { /* default theme stays */ }
      }

      // Apply starter template
      if (template !== 'blank') {
        try {
          const tr = await api.post(`/sites/${siteId}/apply-template`, {
            template,
            // business type → AI-tailored, industry-specific copy + images (Full Site)
            topic: template === 'full' && topic.trim() ? topic.trim() : undefined,
          });
          setTemplateResult(tr.data.data.message || 'Template applied');
        } catch {
          setTemplateResult('Template applied partially — you can add content manually.');
        }
      } else {
        try {
          await api.post(`/sites/${siteId}/apply-template`, { template: 'blank' });
        } catch { /* ignore */ }
        setTemplateResult('Blank site created with home page.');
      }

      setStep(5); // Success screen
      setCreating(false);
    } catch (e: any) {
      setError(e?.response?.data?.message || 'Failed to create site');
      setCreating(false);
    }
  };

  const totalSteps = 4;
  const isSuccess = step === 5;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-base-100 rounded-2xl shadow-2xl w-[520px] max-w-[95vw] overflow-hidden" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-base-300/20">
          <h2 className="text-base font-semibold text-base-content">Create New Site</h2>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={16} /></button>
        </div>

        {/* Steps indicator */}
        {!isSuccess && (
          <div className="flex items-center gap-2 px-6 py-3 bg-base-200/50">
            {STEP_LABELS.map((label, idx) => {
              const s = idx + 1;
              return (
                <div key={s} className={`flex items-center gap-1.5 text-xs font-medium ${step >= s ? 'text-primary' : 'text-base-content/30'}`}>
                  <div className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${step >= s ? 'bg-primary text-primary-content' : 'bg-base-300/50'}`}>{s}</div>
                  {label}
                  {s < totalSteps && <div className={`w-6 h-px ${step > s ? 'bg-primary' : 'bg-base-300/30'}`} />}
                </div>
              );
            })}
          </div>
        )}

        {/* Content */}
        <div className="px-6 py-5">
          {/* Step 1: Basics */}
          {step === 1 && (
            <div className="space-y-4">
              <div>
                <label className="text-xs font-medium text-base-content/60 mb-1.5 block">Site Name</label>
                <input type="text" value={name} onChange={e => { setName(e.target.value); if (!slug) setSlug(''); }}
                  className="input input-bordered w-full" placeholder="My Awesome Website" autoFocus />
              </div>
              <div>
                <label className="text-xs font-medium text-base-content/60 mb-1.5 block">URL Slug</label>
                <input type="text" value={slug || autoSlug(name)} onChange={e => setSlug(e.target.value)}
                  className="input input-bordered w-full font-mono text-sm" placeholder="my-awesome-website" />
                <p className="text-[10px] text-base-content/30 mt-1">Used for the default URL: {slug || autoSlug(name) || 'slug'}.ensodo.eu</p>
              </div>
            </div>
          )}

          {/* Step 2: Theme selection */}
          {step === 2 && (
            <div className="space-y-3">
              <p className="text-xs text-base-content/50 mb-2">Choose a theme for your site's visual style.</p>
              {themesLoading && (
                <div className="flex items-center justify-center py-8">
                  <span className="loading loading-spinner loading-sm text-base-content/20"></span>
                </div>
              )}
              {!themesLoading && themes.length === 0 && (
                <div className="text-center py-6 text-xs text-base-content/40">No themes available. A default theme will be applied.</div>
              )}
              {!themesLoading && themes.map(t => (
                <button key={t.id} onClick={() => setSelectedThemeId(t.id)}
                  className={`w-full flex items-center gap-3 p-3 rounded-lg border text-left transition-colors ${
                    selectedThemeId === t.id ? 'border-primary bg-primary/5' : 'border-base-300/30 hover:border-base-300/60'
                  }`}>
                  <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${
                    selectedThemeId === t.id ? 'bg-primary/10' : 'bg-base-200'
                  }`}>
                    <Palette size={18} className={selectedThemeId === t.id ? 'text-primary' : 'text-base-content/30'} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className={`text-sm font-medium ${selectedThemeId === t.id ? 'text-primary' : 'text-base-content/70'}`}>{t.name}</div>
                    {t.description && <div className="text-[11px] text-base-content/40 truncate">{t.description}</div>}
                    <div className="flex items-center gap-1.5 mt-1">
                      {t.modes.map(m => (
                        <span key={m} className="text-[9px] px-1.5 py-0.5 rounded bg-base-200 text-base-content/40">{m}</span>
                      ))}
                    </div>
                  </div>
                  {selectedThemeId === t.id && <Check size={16} className="text-primary shrink-0" />}
                </button>
              ))}
              <p className="text-[10px] text-base-content/25 mt-1">Theme can be changed later in Site Settings.</p>
            </div>
          )}

          {/* Step 3: Template + Preview */}
          {step === 3 && (
            <div className="space-y-3">
              <p className="text-xs text-base-content/50 mb-2">Choose a starting point for your site.</p>
              <div className="grid grid-cols-2 gap-2">
                {TEMPLATE_OPTIONS.map(t => (
                  <button key={t.id} onClick={() => setTemplate(t.id)}
                    className={`flex items-center gap-2 p-2.5 rounded-lg border text-left transition-colors ${
                      template === t.id ? 'border-primary bg-primary/5' : 'border-base-300/30 hover:border-base-300/60'
                    }`}>
                    <t.icon size={16} className={`shrink-0 ${template === t.id ? 'text-primary' : 'text-base-content/30'}`} />
                    <div className="min-w-0">
                      <div className={`text-xs font-medium ${template === t.id ? 'text-primary' : 'text-base-content/70'}`}>{t.label}</div>
                      <div className="text-[10px] text-base-content/40 truncate">{t.desc}</div>
                    </div>
                  </button>
                ))}
              </div>

              {/* Business type → AI-tailored copy + images (Full Site only) */}
              {template === 'full' && (
                <div className="rounded-lg border border-primary/20 bg-primary/5 p-3 space-y-1.5">
                  <label className="text-xs font-medium text-base-content/70">What kind of business is this?</label>
                  <input
                    type="text"
                    value={topic}
                    onChange={e => setTopic(e.target.value)}
                    placeholder="e.g. HVAC company, day spa, boutique hotel, plumbing…"
                    className="input input-bordered input-sm w-full text-xs"
                  />
                  <p className="text-[10px] text-base-content/45">
                    {topic.trim()
                      ? 'Pages will be written for this business, with matching photos. Takes a few extra seconds.'
                      : 'Optional — leave blank for generic starter copy. Fill it in and AI writes industry-specific pages + images.'}
                  </p>
                </div>
              )}

              {/* Template preview */}
              {TEMPLATE_PREVIEWS[template] && (
                <div className="mt-3 pt-3 border-t border-base-300/20">
                  <div className="flex items-center gap-1.5 mb-2">
                    <Eye size={12} className="text-base-content/40" />
                    <span className="text-[11px] font-medium text-base-content/50">
                      Preview — {TEMPLATE_PREVIEWS[template].length} page{TEMPLATE_PREVIEWS[template].length !== 1 ? 's' : ''} will be created
                    </span>
                  </div>
                  <div className="grid grid-cols-2 gap-2 max-h-[200px] overflow-y-auto pr-1">
                    {TEMPLATE_PREVIEWS[template].map(page => (
                      <div key={page.slug}>
                        <div className="text-[10px] font-medium text-base-content/50 mb-0.5">{page.title}</div>
                        <PageWireframe page={page} />
                      </div>
                    ))}
                  </div>
                </div>
              )}
              <p className="text-[10px] text-base-content/25 mt-1">Template content can be customized after creation.</p>
            </div>
          )}

          {/* Step 4: Confirm */}
          {step === 4 && (
            <div className="space-y-3">
              <div className="bg-base-200/50 rounded-lg p-4 space-y-2">
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Name</span><span className="font-medium text-base-content">{name}</span></div>
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Slug</span><span className="font-mono text-base-content/80">{slug || autoSlug(name)}</span></div>
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Theme</span><span className="text-base-content/80">{selectedTheme?.name || 'Default'}</span></div>
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Template</span><span className="text-base-content/80">{TEMPLATE_OPTIONS.find(t => t.id === template)?.label || template}</span></div>
              </div>
              {error && <div className="text-xs text-error bg-error/10 rounded-lg p-2">{error}</div>}
            </div>
          )}

          {/* Step 5: Success */}
          {isSuccess && createdSiteId && (
            <div className="text-center space-y-4">
              <div className="w-12 h-12 bg-success/10 rounded-full flex items-center justify-center mx-auto">
                <Globe className="h-6 w-6 text-success" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-base-content">Site Created!</h3>
                <p className="text-sm text-base-content/50 mt-1">{templateResult}</p>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <button onClick={() => navigate(`/sites/${createdSiteId}/pages`)} className="btn btn-primary btn-sm gap-1">
                  <FileText size={14} /> Open Pages
                </button>
                <button onClick={() => navigate(`/sites/${createdSiteId}/settings`)} className="btn btn-ghost btn-sm gap-1">
                  <Layout size={14} /> Settings
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        {!isSuccess && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-base-300/20 bg-base-200/30">
            <button onClick={() => step > 1 ? setStep(step - 1) : onClose()}
              className="btn btn-ghost btn-sm">{step === 1 ? 'Cancel' : 'Back'}</button>
            {step < totalSteps ? (
              <button onClick={() => setStep(step + 1)} disabled={step === 1 && !name.trim()}
                className="btn btn-primary btn-sm gap-1">Next <ArrowRight size={14} /></button>
            ) : (
              <button onClick={handleCreate} disabled={creating || !name.trim()}
                className="btn btn-primary btn-sm gap-1">{creating ? 'Creating...' : 'Create Site'}</button>
            )}
          </div>
        )}
        {isSuccess && (
          <div className="px-6 py-3 border-t border-base-300/20 bg-base-200/30 text-center">
            <button onClick={onClose} className="btn btn-ghost btn-sm">Close</button>
          </div>
        )}
      </div>
    </div>
  );
}
