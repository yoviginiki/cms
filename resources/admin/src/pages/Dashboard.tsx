import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Globe, FileText, Newspaper, ExternalLink, Download, ArrowRight, X, Palette, Layout } from 'lucide-react';
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

function SiteWizard({ onClose }: { onClose: () => void; onCreate?: (id: string) => void }) {
  const [step, setStep] = useState(1);
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [template, setTemplate] = useState('blank');
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');
  const [createdSiteId, setCreatedSiteId] = useState<string | null>(null);
  const [templateResult, setTemplateResult] = useState('');
  const navigate = useNavigate();

  const autoSlug = (n: string) => n.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

  const handleCreate = async () => {
    if (!name.trim()) return;
    setCreating(true);
    setError('');
    try {
      const r = await sites.create({ name: name.trim(), slug: slug || autoSlug(name) });
      const siteId = r.data.data.id;
      setCreatedSiteId(siteId);

      // Apply starter template
      if (template !== 'blank') {
        try {
          const tr = await api.post(`/sites/${siteId}/apply-template`, { template });
          setTemplateResult(tr.data.data.message || 'Template applied');
        } catch {
          setTemplateResult('Template applied partially — you can add content manually.');
        }
      } else {
        // Blank — still create a home page
        try {
          await api.post(`/sites/${siteId}/apply-template`, { template: 'blank' });
        } catch { /* ignore */ }
        setTemplateResult('Blank site created with home page.');
      }

      setStep(4); // Success screen
      setCreating(false);
    } catch (e: any) {
      setError(e?.response?.data?.message || 'Failed to create site');
      setCreating(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-base-100 rounded-2xl shadow-2xl w-[520px] max-w-[95vw] overflow-hidden" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-base-300/20">
          <h2 className="text-base font-semibold text-base-content">Create New Site</h2>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={16} /></button>
        </div>

        {/* Steps indicator */}
        {step < 4 && (
          <div className="flex items-center gap-2 px-6 py-3 bg-base-200/50">
            {[1, 2, 3].map(s => (
              <div key={s} className={`flex items-center gap-1.5 text-xs font-medium ${step >= s ? 'text-primary' : 'text-base-content/30'}`}>
                <div className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${step >= s ? 'bg-primary text-primary-content' : 'bg-base-300/50'}`}>{s}</div>
                {s === 1 ? 'Basics' : s === 2 ? 'Template' : 'Confirm'}
                {s < 3 && <div className={`w-6 h-px ${step > s ? 'bg-primary' : 'bg-base-300/30'}`} />}
              </div>
            ))}
          </div>
        )}

        {/* Content */}
        <div className="px-6 py-5">
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

          {step === 2 && (
            <div className="space-y-3">
              <p className="text-xs text-base-content/50 mb-2">Choose a starting point for your site.</p>
              {[
                { id: 'blank', icon: Layout, label: 'Blank Site', desc: 'Start from scratch with an empty homepage' },
                { id: 'blog', icon: Newspaper, label: 'Blog', desc: 'Home, About, Contact + blog with latest posts' },
                { id: 'portfolio', icon: Palette, label: 'Portfolio', desc: 'Home, About, Work gallery + Contact' },
                { id: 'business', icon: Globe, label: 'Business', desc: 'Home, About, Services, Team + Contact' },
              ].map(t => (
                <button key={t.id} onClick={() => setTemplate(t.id)}
                  className={`w-full flex items-center gap-3 p-3 rounded-lg border text-left transition-colors ${
                    template === t.id ? 'border-primary bg-primary/5' : 'border-base-300/30 hover:border-base-300/60'
                  }`}>
                  <t.icon size={20} className={template === t.id ? 'text-primary' : 'text-base-content/30'} />
                  <div>
                    <div className={`text-sm font-medium ${template === t.id ? 'text-primary' : 'text-base-content/70'}`}>{t.label}</div>
                    <div className="text-[11px] text-base-content/40">{t.desc}</div>
                  </div>
                </button>
              ))}
              <p className="text-[10px] text-base-content/25 mt-1">Template content can be customized after creation.</p>
            </div>
          )}

          {step === 3 && (
            <div className="space-y-3">
              <div className="bg-base-200/50 rounded-lg p-4 space-y-2">
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Name</span><span className="font-medium text-base-content">{name}</span></div>
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Slug</span><span className="font-mono text-base-content/80">{slug || autoSlug(name)}</span></div>
                <div className="flex justify-between text-sm"><span className="text-base-content/50">Template</span><span className="text-base-content/80">{template === 'blank' ? 'Blank Site' : template === 'blog' ? 'Blog' : 'Portfolio'}</span></div>
              </div>
              {error && <div className="text-xs text-error bg-error/10 rounded-lg p-2">{error}</div>}
            </div>
          )}

          {step === 4 && createdSiteId && (
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
        {step < 4 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-base-300/20 bg-base-200/30">
            <button onClick={() => step > 1 ? setStep(step - 1) : onClose()}
              className="btn btn-ghost btn-sm">{step === 1 ? 'Cancel' : 'Back'}</button>
            {step < 3 ? (
              <button onClick={() => setStep(step + 1)} disabled={step === 1 && !name.trim()}
                className="btn btn-primary btn-sm gap-1">Next <ArrowRight size={14} /></button>
            ) : (
              <button onClick={handleCreate} disabled={creating || !name.trim()}
                className="btn btn-primary btn-sm gap-1">{creating ? 'Creating...' : 'Create Site'}</button>
            )}
          </div>
        )}
        {step === 4 && (
          <div className="px-6 py-3 border-t border-base-300/20 bg-base-200/30 text-center">
            <button onClick={onClose} className="btn btn-ghost btn-sm">Close</button>
          </div>
        )}
      </div>
    </div>
  );
}
