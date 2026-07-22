import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import {
  Globe, Loader2, Check, X, RotateCcw, Rocket, Trash2, Upload, Link2,
  AlertTriangle, FileText, Palette, List, SkipForward, Circle,
} from 'lucide-react';
import { siteWizard, type SiteWizardSession, type SiteWizardStep } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

const apiErr = (e: any) => e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

/**
 * Site Wizard — the integrated "website creator": point it at a live site or
 * upload a design export (ZIP of HTML/CSS, e.g. from Canva) and it builds a
 * COMPLETE site — theme, pages as real blocks, navigation, homepage, media —
 * all editable with the normal builder afterwards.
 */
export default function SiteWizardPage() {
  const { sessionId } = useParams();
  const navigate = useNavigate();
  const { toast } = useToast();

  const [session, setSession] = useState<SiteWizardSession | null>(null);
  const [method, setMethod] = useState<'url' | 'zip'>('url');
  const [url, setUrl] = useState('');
  const [name, setName] = useState('');
  const [maxPages, setMaxPages] = useState(15);
  const fileRef = useRef<HTMLInputElement>(null);

  // Resume an in-flight session by URL (/site-wizard/:sessionId).
  useQuery({
    queryKey: ['site-wizard', sessionId],
    queryFn: async () => {
      const r = await siteWizard.get(sessionId!);
      setSession(r.data.data);
      return r.data.data;
    },
    enabled: !!sessionId && !session,
  });

  // The build runs on the queue — poll while it's running.
  useEffect(() => {
    if (!session || session.status !== 'running') return;
    const id = session.id;
    const t = setInterval(async () => {
      try {
        const r = await siteWizard.get(id);
        setSession(r.data.data);
        if (r.data.data.status !== 'running') clearInterval(t);
      } catch { /* transient — keep polling */ }
    }, 2000);
    return () => clearInterval(t);
  }, [session?.id, session?.status]);

  const start = (s: SiteWizardSession) => {
    setSession(s);
    navigate(`/site-wizard/${s.id}`, { replace: true });
  };

  const startUrl = useMutation({
    mutationFn: () => siteWizard.fromUrl(url.trim(), name.trim() || undefined, maxPages),
    onSuccess: (r) => start(r.data.data),
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const startZip = useMutation({
    mutationFn: (file: File) => siteWizard.fromZip(file, name.trim() || undefined),
    onSuccess: (r) => start(r.data.data),
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const accept = useMutation({
    mutationFn: () => siteWizard.accept(session!.id),
    onSuccess: (r) => {
      const site = r.data.data.site;
      toast({ type: 'success', message: `“${site.name}” is live in your sites — pages published.` });
      navigate(`/sites/${site.slug}/pages`);
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const abandon = useMutation({
    mutationFn: () => siteWizard.abandon(session!.id),
    onSuccess: () => {
      toast({ type: 'success', message: 'Build discarded.' });
      setSession(null);
      navigate('/site-wizard', { replace: true });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const retry = useMutation({
    mutationFn: () => siteWizard.retry(session!.id),
    onSuccess: (r) => setSession(r.data.data),
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  // ── Input (no session yet) ──
  if (!session) {
    return (
      <div className="max-w-2xl mx-auto py-10">
        <div className="flex items-center gap-2 mb-1">
          <Globe className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold text-base-content">Site Wizard</h1>
        </div>
        <p className="text-sm text-base-content/50 mb-8">
          Turn an existing design into a complete website — theme, pages, navigation and images —
          built with real blocks you can edit afterwards. Point it at a live site, or upload a
          design exported as HTML (from Canva: Share → Download → HTML).
        </p>

        <div className="join w-full mb-5">
          {([
            { k: 'url' as const, icon: Link2, label: 'From a live website' },
            { k: 'zip' as const, icon: Upload, label: 'From a design export (ZIP)' },
          ]).map((m) => (
            <button
              key={m.k}
              className={`join-item btn btn-sm flex-1 ${method === m.k ? 'btn-primary' : 'btn-ghost border-base-300/60'}`}
              onClick={() => setMethod(m.k)}
            >
              <m.icon size={14} /> {m.label}
            </button>
          ))}
        </div>

        <label className="form-control w-full mb-4">
          <span className="label-text text-xs mb-1">Site name (optional — detected from the design if empty)</span>
          <input
            className="input input-bordered input-sm w-full"
            placeholder="My new website"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </label>

        {method === 'url' ? (
          <>
            <label className="form-control w-full mb-4">
              <span className="label-text text-xs mb-1">Website address</span>
              <input
                className="input input-bordered w-full"
                placeholder="https://example.com"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && url.trim() && startUrl.mutate()}
              />
            </label>
            <label className="form-control w-full mb-6">
              <span className="label-text text-xs mb-1">Pages to import (max 20)</span>
              <input
                type="number" min={1} max={20}
                className="input input-bordered input-sm w-28"
                value={maxPages}
                onChange={(e) => setMaxPages(Math.max(1, Math.min(20, Number(e.target.value) || 15)))}
              />
            </label>
            <button
              className="btn btn-primary w-full"
              disabled={!url.trim() || startUrl.isPending}
              onClick={() => startUrl.mutate()}
            >
              {startUrl.isPending ? <Loader2 size={16} className="animate-spin" /> : <Rocket size={16} />}
              Build my website
            </button>
          </>
        ) : (
          <>
            <button
              className="border-2 border-dashed border-base-300 rounded-box w-full py-10 flex flex-col items-center gap-2 text-base-content/50 hover:border-primary/50 hover:text-base-content/80 transition-colors mb-2"
              onClick={() => fileRef.current?.click()}
              disabled={startZip.isPending}
            >
              {startZip.isPending
                ? <Loader2 size={22} className="animate-spin" />
                : <Upload size={22} />}
              <span className="text-sm">Drop or choose your design export (.zip, up to 100&nbsp;MB)</span>
              <span className="text-xs text-base-content/40">HTML, CSS and images — index.html becomes the homepage</span>
            </button>
            <input
              ref={fileRef}
              type="file"
              accept=".zip,application/zip"
              className="hidden"
              onChange={(e) => {
                const f = e.target.files?.[0];
                if (f) startZip.mutate(f);
                e.target.value = '';
              }}
            />
            <p className="text-xs text-base-content/40">
              Web fonts referenced from the internet are substituted with matching open fonts.
            </p>
          </>
        )}
      </div>
    );
  }

  // ── Progress / review ──
  const failed = session.status === 'failed';
  const review = session.status === 'review';
  const running = session.status === 'running';
  const editBase = session.site ? `/sites/${session.site.id}/pages` : null;

  return (
    <div className="max-w-2xl mx-auto py-10">
      <div className="flex items-center gap-2 mb-1">
        <Globe className="h-6 w-6 text-primary" />
        <h1 className="text-2xl font-bold text-base-content">
          {session.title || 'Building your website'}
        </h1>
      </div>
      <p className="text-sm text-base-content/50 mb-6">
        {running && 'Reading the design and building everything — this usually takes a minute or two.'}
        {review && 'Everything is built. Check it over, then create the website — or discard it.'}
        {failed && 'The build hit a problem. You can retry — finished steps are kept.'}
        {session.status === 'accepted' && 'This website has been created.'}
        {session.status === 'abandoned' && 'This build was discarded.'}
      </p>

      {/* Step checklist */}
      <div className="border border-base-300/40 rounded-box bg-base-100 divide-y divide-base-300/30 mb-5">
        {session.steps.map((step) => (
          <div key={step.key} className="px-4 py-2.5 flex items-center gap-3">
            <StepIcon step={step} />
            <div className="flex-1">
              <div className="text-[13px]">{step.label}</div>
              {step.detail && <div className="text-xs text-base-content/45">{step.detail}</div>}
            </div>
            {/* Per-page ticks inside the pages step */}
            {step.key === 'pages' && session.sources.length > 0 && step.status !== 'pending' && (
              <span className="text-xs text-base-content/50">
                {session.sources.filter((s) => s.status === 'done').length}/{session.sources.length}
              </span>
            )}
          </div>
        ))}
      </div>

      {/* Per-page progress */}
      {session.sources.length > 0 && (
        <div className="border border-base-300/40 rounded-box bg-base-100 mb-5">
          <div className="px-4 py-2 text-xs font-medium text-base-content/50 flex items-center gap-2">
            <FileText size={13} /> Pages
          </div>
          <div className="divide-y divide-base-300/30">
            {session.sources.map((src) => (
              <div key={src.ref} className="px-4 py-2 flex items-center gap-3 text-[13px]">
                <SourceIcon status={src.status} />
                <span className="flex-1 truncate">
                  {src.title || src.slug}
                  {src.is_home && <span className="badge badge-ghost badge-xs ml-2">homepage</span>}
                </span>
                {src.status === 'failed' && (
                  <span className="text-xs text-error/80 truncate max-w-[45%]" title={src.error || ''}>{src.error}</span>
                )}
                {review && src.page_id && session.site && (
                  <Link className="link link-primary text-xs" to={`/sites/${session.site.id}/pages/${src.page_id}/edit`}>
                    edit
                  </Link>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Theme preview */}
      {session.theme && (
        <div className="border border-base-300/40 rounded-box bg-base-100 mb-5 px-4 py-3">
          <div className="text-xs font-medium text-base-content/50 flex items-center gap-2 mb-2">
            <Palette size={13} /> Theme — read from the design
          </div>
          <div className="flex items-center gap-1.5 mb-2">
            {Object.entries(session.theme.palette).map(([role, hex]) => (
              <span
                key={role}
                title={`${role} ${hex}`}
                className="w-7 h-7 rounded-full border border-base-300/60 inline-block"
                style={{ backgroundColor: hex }}
              />
            ))}
          </div>
          <p className="text-xs text-base-content/45">
            {[session.theme.typography.display, session.theme.typography.body].filter(Boolean).join(' · ')}
          </p>
        </div>
      )}

      {/* Menu preview */}
      {session.menu && session.menu.length > 0 && (
        <div className="border border-base-300/40 rounded-box bg-base-100 mb-5 px-4 py-3">
          <div className="text-xs font-medium text-base-content/50 flex items-center gap-2 mb-2">
            <List size={13} /> Navigation
          </div>
          <div className="flex flex-wrap gap-1.5">
            {session.menu.map((item, i) => (
              <span key={i} className="badge badge-ghost badge-sm">{item.label}</span>
            ))}
          </div>
        </div>
      )}

      {failed && session.error && (
        <div className="alert alert-warning text-sm mb-5">
          <AlertTriangle size={16} /> {session.error}
        </div>
      )}

      {/* Actions */}
      <div className="flex gap-2">
        {review && (
          <>
            <button className="btn btn-primary flex-1" disabled={accept.isPending} onClick={() => accept.mutate()}>
              {accept.isPending ? <Loader2 size={16} className="animate-spin" /> : <Rocket size={16} />}
              Create website
            </button>
            <button
              className="btn btn-ghost text-error"
              disabled={abandon.isPending}
              onClick={() => window.confirm('Discard this build? The whole site — pages, theme and menu — will be deleted.') && abandon.mutate()}
            >
              <Trash2 size={16} /> Discard
            </button>
          </>
        )}
        {failed && (
          <>
            <button className="btn btn-primary flex-1" disabled={retry.isPending} onClick={() => retry.mutate()}>
              {retry.isPending ? <Loader2 size={16} className="animate-spin" /> : <RotateCcw size={16} />}
              Retry the build
            </button>
            <button
              className="btn btn-ghost text-error"
              disabled={abandon.isPending}
              onClick={() => window.confirm('Discard this build? Anything already created will be deleted.') && abandon.mutate()}
            >
              <Trash2 size={16} /> Discard
            </button>
          </>
        )}
        {session.status === 'accepted' && editBase && (
          <Link className="btn btn-primary flex-1" to={editBase}>Open the site</Link>
        )}
        {(session.status === 'abandoned' || session.status === 'accepted') && (
          <button className="btn btn-ghost" onClick={() => { setSession(null); navigate('/site-wizard', { replace: true }); }}>
            Start another
          </button>
        )}
      </div>
    </div>
  );
}

function StepIcon({ step }: { step: SiteWizardStep }) {
  if (step.status === 'done') return <Check size={15} className="text-success shrink-0" />;
  if (step.status === 'running') return <Loader2 size={15} className="animate-spin text-primary shrink-0" />;
  if (step.status === 'failed') return <X size={15} className="text-error shrink-0" />;
  if (step.status === 'skipped') return <SkipForward size={15} className="text-base-content/30 shrink-0" />;
  return <Circle size={15} className="text-base-content/20 shrink-0" />;
}

function SourceIcon({ status }: { status: string }) {
  if (status === 'done') return <Check size={14} className="text-success shrink-0" />;
  if (status === 'building') return <Loader2 size={14} className="animate-spin text-primary shrink-0" />;
  if (status === 'failed') return <AlertTriangle size={14} className="text-warning shrink-0" />;
  return <Circle size={14} className="text-base-content/20 shrink-0" />;
}
