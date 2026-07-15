import { useState, useRef, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import {
  Wand2, Link2, Upload, Loader2, Sparkles, Check, RotateCcw, Send, Monitor, Smartphone,
  AlertTriangle, Layout, FileText, MessageSquare, Rocket, ImageIcon, Download,
} from 'lucide-react';
import { pageWizard, type PageWizardSession } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

const apiErr = (e: any) => e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

type StartMethod = 'url' | 'upload' | 'describe';

export default function PageWizardPage() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const { toast } = useToast();

  const [session, setSession] = useState<PageWizardSession | null>(null);
  const [method, setMethod] = useState<StartMethod>('url');
  const [url, setUrl] = useState('');
  const [urlGoal, setUrlGoal] = useState<'dom' | 'layout' | 'content'>('dom');
  const [hint, setHint] = useState('');
  const [description, setDescription] = useState('');
  const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
  const [previewNonce, setPreviewNonce] = useState(0);
  const fileRef = useRef<HTMLInputElement>(null);

  const bump = () => setPreviewNonce((n) => n + 1);

  // Layout-from-URL captures a screenshot on the queue worker, so the session
  // starts as `capturing`. Poll every 2s until it flips to drafting/failed.
  useEffect(() => {
    if (!session || session.status !== 'capturing') return;
    const id = session.id;
    const t = setInterval(async () => {
      try {
        const r = await pageWizard.get(siteId, id);
        const data = r.data.data;
        setSession(data);
        if (data.status !== 'capturing') {
          clearInterval(t);
          if (data.status === 'drafting') bump();
        }
      } catch { /* transient — keep polling */ }
    }, 2000);
    return () => clearInterval(t);
  }, [session?.id, session?.status, siteId]);

  const startUrl = useMutation({
    mutationFn: () => pageWizard.fromUrl(siteId, url.trim(), urlGoal, hint.trim() || undefined),
    onSuccess: (r) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const startUpload = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData();
      fd.append('image', file);
      if (hint.trim()) fd.append('hint', hint.trim());
      return pageWizard.fromUpload(siteId, fd);
    },
    onSuccess: (r) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const startDescribe = useMutation({
    mutationFn: () => pageWizard.fromDescribe(siteId, description.trim()),
    onSuccess: (r) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const nudge = useMutation({
    mutationFn: (instruction: string) => pageWizard.nudge(siteId, session!.id, instruction),
    onSuccess: (r) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const accept = useMutation({
    mutationFn: (publish: boolean) => pageWizard.accept(siteId, session!.id, publish),
    onSuccess: (r) => {
      const page = r.data.data.page;
      toast({ type: 'success', message: `“${page.title}” saved — opening the editor.` });
      navigate(`/sites/${siteId}/pages/${page.id}/edit`);
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const abandon = useMutation({
    mutationFn: () => pageWizard.abandon(siteId, session!.id),
    onSuccess: () => restart(),
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const busy = startUrl.isPending || startUpload.isPending || startDescribe.isPending;

  const restart = () => {
    setSession(null);
    setUrl('');
    setHint('');
    setDescription('');
    setUrlGoal('dom');
  };

  // ── Input panel (no session yet) ──
  if (!session) {
    return (
      <div className="max-w-2xl mx-auto py-10">
        <div className="flex items-center gap-2 mb-1">
          <Wand2 className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold text-base-content">Page Wizard</h1>
        </div>
        <p className="text-sm text-base-content/50 mb-8">
          Build a page from a link you like, a screenshot, or just a description. It drafts the page
          with real blocks — refine it in plain language, then keep it (and edit further) or publish.
        </p>

        {/* Segmented start-method picker */}
        <div className="join w-full mb-5">
          {([
            { k: 'url' as const, icon: Link2, label: 'From URL' },
            { k: 'upload' as const, icon: ImageIcon, label: 'From screenshot' },
            { k: 'describe' as const, icon: MessageSquare, label: 'Describe it' },
          ]).map((m) => (
            <button key={m.k} onClick={() => setMethod(m.k)} disabled={busy}
              className={`btn btn-sm join-item flex-1 gap-1.5 ${method === m.k ? 'btn-primary' : 'btn-outline'}`}>
              <m.icon size={14} /> {m.label}
            </button>
          ))}
        </div>

        <div className="bg-base-100 border border-base-300 p-6 space-y-5">
          {/* From URL */}
          {method === 'url' && (
            <>
              <div>
                <label className="text-xs font-medium text-base-content/60 flex items-center gap-1.5 mb-1.5"><Link2 size={13} /> A page you like</label>
                <input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://example.com/pricing"
                  onKeyDown={(e) => e.key === 'Enter' && url.trim() && startUrl.mutate()}
                  className="input input-bordered input-sm w-full text-[13px]" />
              </div>

              <div>
                <label className="text-xs font-medium text-base-content/60 mb-1.5 block">How should it build the page?</label>
                <div className="join w-full">
                  <button onClick={() => setUrlGoal('dom')}
                    className={`btn btn-sm join-item flex-1 gap-1 ${urlGoal === 'dom' ? 'btn-primary' : 'btn-outline'}`}>
                    <Download size={13} /> Import
                  </button>
                  <button onClick={() => setUrlGoal('layout')}
                    className={`btn btn-sm join-item flex-1 gap-1 ${urlGoal === 'layout' ? 'btn-primary' : 'btn-outline'}`}>
                    <Layout size={13} /> Layout
                  </button>
                  <button onClick={() => setUrlGoal('content')}
                    className={`btn btn-sm join-item flex-1 gap-1 ${urlGoal === 'content' ? 'btn-primary' : 'btn-outline'}`}>
                    <FileText size={13} /> Content
                  </button>
                </div>
                <p className="text-[11px] text-base-content/40 mt-1.5 leading-relaxed">
                  {urlGoal === 'dom'
                    ? 'Reads the page’s real structure, text and images and rebuilds it as blocks. Free, fast, no AI — the recommended default.'
                    : urlGoal === 'layout'
                      ? 'Screenshots the page and has AI redraw its structure with blocks. Uses AI credits.'
                      : 'AI pulls the page’s text into a new page laid out with your theme. Uses AI credits.'}
                </p>
              </div>

              <div>
                <label className="text-xs font-medium text-base-content/60 mb-1.5 block">A nudge for the direction (optional)</label>
                <input value={hint} onChange={(e) => setHint(e.target.value)} placeholder="e.g. tighter hero, add a testimonials section"
                  className="input input-bordered input-sm w-full text-[13px]" />
              </div>

              <button onClick={() => startUrl.mutate()} disabled={busy || !url.trim()}
                className="btn btn-primary btn-sm gap-1.5 w-full">
                {startUrl.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Sparkles className="h-3.5 w-3.5" />}
                Build the page
              </button>
            </>
          )}

          {/* From screenshot */}
          {method === 'upload' && (
            <>
              <p className="text-[12px] text-base-content/50 leading-relaxed">
                Upload a screenshot of a page and the wizard redraws its structure with blocks.
              </p>
              <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" className="hidden"
                onChange={(e) => { const f = e.target.files?.[0]; if (f) startUpload.mutate(f); }} />
              <button onClick={() => fileRef.current?.click()} disabled={busy}
                className="btn btn-outline btn-sm gap-1.5 w-full h-24 flex-col border-dashed">
                {startUpload.isPending
                  ? <Loader2 className="h-5 w-5 animate-spin" />
                  : <Upload className="h-5 w-5" />}
                <span className="text-[12px]">Drop or choose a PNG / JPG / WebP (≤8MB)</span>
              </button>
              <div>
                <label className="text-xs font-medium text-base-content/60 mb-1.5 block">A nudge for the direction (optional)</label>
                <input value={hint} onChange={(e) => setHint(e.target.value)} placeholder="e.g. warmer, more editorial"
                  className="input input-bordered input-sm w-full text-[13px]" />
              </div>
            </>
          )}

          {/* Describe it */}
          {method === 'describe' && (
            <>
              <label className="text-xs font-medium text-base-content/60 flex items-center gap-1.5 mb-1.5">
                <MessageSquare size={13} /> Describe the page
              </label>
              <textarea value={description} onChange={(e) => setDescription(e.target.value)}
                rows={4} placeholder="A pricing page for my SaaS with three tiers, a comparison table, and an FAQ…"
                className="textarea textarea-bordered w-full text-[13px] resize-none leading-snug" />
              <button onClick={() => startDescribe.mutate()}
                disabled={busy || description.trim().length < 8}
                className="btn btn-primary btn-sm gap-1.5 w-full">
                {startDescribe.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Sparkles className="h-3.5 w-3.5" />}
                Build the page
              </button>
            </>
          )}

          {busy && (
            <div className="flex items-center gap-2 text-[13px] text-base-content/50">
              <Loader2 className="h-4 w-4 animate-spin" /> Drafting your page…
            </div>
          )}
        </div>
      </div>
    );
  }

  // ── Capturing (layout-from-URL running on the queue worker) ──
  if (session.status === 'capturing') {
    return (
      <div className="max-w-md mx-auto py-24 text-center">
        <Loader2 className="h-8 w-8 text-primary animate-spin mx-auto mb-4" />
        <h2 className="text-lg font-semibold text-base-content mb-1">Reading the page…</h2>
        <p className="text-sm text-base-content/50">
          Capturing {session.reference_url && (
            <span className="text-base-content/70 break-all">{session.reference_url}</span>
          )} and redrawing it with blocks. This usually takes a few seconds.
        </p>
      </div>
    );
  }

  // ── Capture failed — offer to switch to screenshot upload ──
  if (session.status === 'capture_failed') {
    return (
      <div className="max-w-md mx-auto py-24 text-center">
        <AlertTriangle className="h-8 w-8 text-warning mx-auto mb-4" />
        <h2 className="text-lg font-semibold text-base-content mb-1">Couldn’t read that page</h2>
        <p className="text-sm text-base-content/60 mb-6">
          {session.error || 'Something went wrong reading that page — try uploading a screenshot instead.'}
        </p>
        <div className="flex items-center justify-center gap-2">
          <button onClick={() => { restart(); setMethod('upload'); }} className="btn btn-primary btn-sm gap-1.5">
            <Upload className="h-3.5 w-3.5" /> Upload a screenshot
          </button>
          <button onClick={restart} className="btn btn-ghost btn-sm gap-1.5">
            <RotateCcw className="h-3.5 w-3.5" /> Start over
          </button>
        </div>
      </div>
    );
  }

  // ── Working panel (drafting / accepted) ──
  const previewSrc = session.preview_path ? `${session.preview_path}?v=${previewNonce}` : null;
  const accepted = session.status === 'accepted';
  const regenerating = nudge.isPending;

  return (
    <div className="flex h-[calc(100vh-4rem)] gap-4 p-2">
      {/* left: conversation */}
      <div className="w-96 shrink-0 flex flex-col bg-base-100 border border-base-300">
        <div className="px-4 py-3 border-b border-base-300">
          <div className="flex items-center gap-2">
            <Wand2 className="h-4 w-4 text-primary" />
            <h2 className="text-[15px] font-semibold text-base-content truncate">{session.title || 'Your page'}</h2>
          </div>
          <div className="mt-1 flex flex-wrap gap-1 text-[10px]">
            <span className="badge badge-ghost badge-sm capitalize">{session.source}</span>
            <span className="badge badge-ghost badge-sm capitalize">{session.mode}</span>
          </div>
        </div>

        <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3">
          {session.transcript.map((m, i) => (
            <div key={i} className={m.role === 'user' ? 'text-right' : ''}>
              <div className={`inline-block text-[13px] leading-relaxed px-3 py-2 max-w-[90%] ${
                m.role === 'user' ? 'bg-primary/10 text-base-content' : 'bg-base-200 text-base-content/80'}`}>
                {m.text}
              </div>
            </div>
          ))}
          {regenerating && (
            <div className="flex items-center gap-2 text-[12px] text-base-content/45">
              <Loader2 className="h-3.5 w-3.5 animate-spin" /> Reworking the page…
            </div>
          )}
        </div>

        {!accepted ? (
          <>
            <NudgeInput onSend={(t) => nudge.mutate(t)} disabled={nudge.isPending} />
            <div className="px-3 pt-2 pb-1 flex items-center justify-between">
              <span className="text-[10px] text-base-content/35">{session.total_tokens.toLocaleString()} tokens used</span>
            </div>
            <div className="p-3 pt-1 border-t border-base-300 flex gap-2">
              <button onClick={() => accept.mutate(false)} disabled={accept.isPending}
                className="btn btn-primary btn-sm flex-1 gap-1.5">
                {accept.isPending && !accept.variables ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                Keep page
              </button>
              <button onClick={() => accept.mutate(true)} disabled={accept.isPending}
                className="btn btn-outline btn-sm gap-1.5" title="Keep and publish">
                <Rocket className="h-3.5 w-3.5" /> Publish
              </button>
              <button onClick={() => abandon.mutate()} disabled={abandon.isPending}
                className="btn btn-ghost btn-sm gap-1.5" title="Discard">
                {abandon.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RotateCcw className="h-3.5 w-3.5" />}
              </button>
            </div>
          </>
        ) : (
          <div className="p-3 border-t border-base-300 text-[13px] text-success flex items-center gap-1.5">
            <Check className="h-4 w-4" /> Saved as a page.
          </div>
        )}
      </div>

      {/* right: live preview */}
      <div className="flex-1 flex flex-col bg-base-100 border border-base-300 min-w-0">
        <div className="px-4 py-2 border-b border-base-300 flex items-center justify-between">
          <span className="text-[12px] text-base-content/50 flex items-center gap-2">
            Live preview — exactly what publishes
            {regenerating && <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />}
          </span>
          <div className="join">
            <button onClick={() => setDevice('desktop')} className={`btn btn-xs join-item ${device === 'desktop' ? 'btn-primary' : 'btn-ghost'}`}><Monitor size={13} /></button>
            <button onClick={() => setDevice('mobile')} className={`btn btn-xs join-item ${device === 'mobile' ? 'btn-primary' : 'btn-ghost'}`}><Smartphone size={13} /></button>
          </div>
        </div>
        <div className="flex-1 overflow-auto bg-base-200 flex justify-center p-4 relative">
          {regenerating && (
            <div className="absolute inset-0 bg-base-200/60 backdrop-blur-[1px] z-10 flex items-center justify-center">
              <div className="flex items-center gap-2 text-[13px] text-base-content/60 bg-base-100 border border-base-300 px-4 py-2 shadow-sm">
                <Loader2 className="h-4 w-4 animate-spin" /> Regenerating…
              </div>
            </div>
          )}
          {previewSrc ? (
            <iframe key={`${previewNonce}-${device}`} title="Page preview" src={previewSrc}
              className="border border-base-300 bg-white shadow-sm"
              style={{ width: device === 'mobile' ? 390 : 1200, minHeight: 760, height: '100%', maxWidth: '100%' }} />
          ) : (
            <div className="flex items-center justify-center text-[13px] text-base-content/40">
              No preview available yet.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function NudgeInput({ onSend, disabled }: { onSend: (t: string) => void; disabled: boolean }) {
  const [text, setText] = useState('');
  const send = () => { const t = text.trim(); if (t && !disabled) { onSend(t); setText(''); } };
  return (
    <div className="px-3 pt-3">
      <div className="flex items-end gap-2">
        <textarea value={text} onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } }}
          rows={2} placeholder="Refine: e.g. “make the hero taller”, “add a pricing section”"
          className="textarea textarea-bordered flex-1 text-[13px] resize-none leading-snug" />
        <button onClick={send} disabled={disabled || !text.trim()} className="btn btn-primary btn-sm btn-square">
          <Send className="h-3.5 w-3.5" />
        </button>
      </div>
      <div className="flex flex-wrap gap-1 mt-1.5">
        {['make the hero taller', 'add a pricing section', 'add a FAQ', 'more whitespace', 'add a CTA'].map((q) => (
          <button key={q} onClick={() => !disabled && onSend(q)} disabled={disabled}
            className="text-[10px] px-2 py-0.5 border border-base-300 text-base-content/50 hover:text-primary hover:border-primary/40 disabled:opacity-40">
            {q}
          </button>
        ))}
      </div>
    </div>
  );
}
