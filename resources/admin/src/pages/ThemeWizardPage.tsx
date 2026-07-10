import { useState, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Wand2, Link2, Upload, Loader2, Sparkles, Check, RotateCcw, Send, Monitor, Smartphone,
} from 'lucide-react';
import { themeWizard } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

interface WizardSession {
  id: string;
  status: 'drafting' | 'accepted' | 'abandoned';
  source: string;
  title: string;
  reference_url?: string | null;
  transcript: { role: string; text: string; at?: string }[];
  candidate: { name?: string; description?: string; layout?: string; fonts?: { display_font?: string; body_font?: string } | null };
  theme_id?: string | null;
  total_tokens: number;
}

const apiErr = (e: any) => e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

export default function ThemeWizardPage() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const [session, setSession] = useState<WizardSession | null>(null);
  const [url, setUrl] = useState('');
  const [hint, setHint] = useState('');
  const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
  const [previewNonce, setPreviewNonce] = useState(0);
  const fileRef = useRef<HTMLInputElement>(null);

  const bump = () => setPreviewNonce((n) => n + 1);

  const startUrl = useMutation({
    mutationFn: () => themeWizard.fromUrl(siteId, url.trim(), hint.trim() || undefined),
    onSuccess: (r: any) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const startUpload = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData();
      fd.append('image', file);
      if (hint.trim()) fd.append('hint', hint.trim());
      return themeWizard.fromUpload(siteId, fd);
    },
    onSuccess: (r: any) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const nudge = useMutation({
    mutationFn: (instruction: string) => themeWizard.nudge(siteId, session!.id, instruction),
    onSuccess: (r: any) => { setSession(r.data.data); bump(); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const accept = useMutation({
    mutationFn: () => themeWizard.accept(siteId, session!.id),
    onSuccess: (r: any) => {
      queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] });
      toast({ type: 'success', message: `“${r.data.data.name}” saved — opening in Theme Studio.` });
      navigate(`/sites/${siteId}/theme-engine/${r.data.data.theme_id}`);
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const busy = startUrl.isPending || startUpload.isPending;

  if (!session) {
    return (
      <div className="max-w-2xl mx-auto py-10">
        <div className="flex items-center gap-2 mb-1">
          <Wand2 className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold text-base-content">Theme Wizard</h1>
        </div>
        <p className="text-sm text-base-content/50 mb-8">
          Point it at a site whose look you like — it reads the feel and designs you an original theme
          (its own colors and open fonts, never a copy). Refine it in plain language, then keep it.
        </p>

        <div className="bg-base-100 border border-base-300 p-6 space-y-5">
          <div>
            <label className="text-xs font-medium text-base-content/60 flex items-center gap-1.5 mb-1.5"><Link2 size={13} /> A site you like</label>
            <div className="flex gap-2">
              <input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://example.com"
                onKeyDown={(e) => e.key === 'Enter' && url.trim() && startUrl.mutate()}
                className="input input-bordered input-sm flex-1 text-[13px]" />
              <button onClick={() => startUrl.mutate()} disabled={busy || !url.trim()}
                className="btn btn-primary btn-sm gap-1.5">
                {startUrl.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Sparkles className="h-3.5 w-3.5" />}
                Design it
              </button>
            </div>
          </div>

          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1.5 block">…or a screenshot</label>
            <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" className="hidden"
              onChange={(e) => { const f = e.target.files?.[0]; if (f) startUpload.mutate(f); }} />
            <button onClick={() => fileRef.current?.click()} disabled={busy}
              className="btn btn-outline btn-sm gap-1.5 w-full">
              {startUpload.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
              Upload a screenshot
            </button>
          </div>

          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1.5 block">A nudge for the direction (optional)</label>
            <input value={hint} onChange={(e) => setHint(e.target.value)} placeholder="e.g. warmer, more editorial, calmer"
              className="input input-bordered input-sm w-full text-[13px]" />
          </div>

          {busy && (
            <div className="flex items-center gap-2 text-[13px] text-base-content/50">
              <Loader2 className="h-4 w-4 animate-spin" /> Reading the design and drafting your theme…
            </div>
          )}
        </div>
      </div>
    );
  }

  const cand = session.candidate;
  const previewSrc = `${themeWizard.previewUrl(siteId, session.id)}?v=${previewNonce}`;
  const accepted = session.status === 'accepted';

  return (
    <div className="flex h-[calc(100vh-4rem)] gap-4 p-2">
      {/* left: conversation */}
      <div className="w-96 shrink-0 flex flex-col bg-base-100 border border-base-300">
        <div className="px-4 py-3 border-b border-base-300">
          <div className="flex items-center gap-2">
            <Wand2 className="h-4 w-4 text-primary" />
            <h2 className="text-[15px] font-semibold text-base-content truncate">{cand.name || session.title}</h2>
          </div>
          {cand.layout && (
            <div className="mt-1 flex flex-wrap gap-1 text-[10px]">
              <span className="badge badge-ghost badge-sm">{cand.layout}</span>
              {cand.fonts?.display_font && <span className="badge badge-ghost badge-sm">{cand.fonts.display_font}</span>}
              {cand.fonts?.body_font && <span className="badge badge-ghost badge-sm">{cand.fonts.body_font}</span>}
            </div>
          )}
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
          {nudge.isPending && (
            <div className="flex items-center gap-2 text-[12px] text-base-content/45">
              <Loader2 className="h-3.5 w-3.5 animate-spin" /> Reworking the design…
            </div>
          )}
        </div>

        {!accepted ? (
          <>
            <NudgeInput onSend={(t) => nudge.mutate(t)} disabled={nudge.isPending} />
            <div className="p-3 border-t border-base-300 flex gap-2">
              <button onClick={() => accept.mutate()} disabled={accept.isPending}
                className="btn btn-primary btn-sm flex-1 gap-1.5">
                {accept.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                Keep this theme
              </button>
              <button onClick={() => { setSession(null); setUrl(''); setHint(''); }}
                className="btn btn-ghost btn-sm gap-1.5" title="Start over">
                <RotateCcw className="h-3.5 w-3.5" />
              </button>
            </div>
          </>
        ) : (
          <div className="p-3 border-t border-base-300 text-[13px] text-success flex items-center gap-1.5">
            <Check className="h-4 w-4" /> Saved as a theme.
          </div>
        )}
      </div>

      {/* right: live preview */}
      <div className="flex-1 flex flex-col bg-base-100 border border-base-300 min-w-0">
        <div className="px-4 py-2 border-b border-base-300 flex items-center justify-between">
          <span className="text-[12px] text-base-content/50">Live preview — exactly what publishes</span>
          <div className="join">
            <button onClick={() => setDevice('desktop')} className={`btn btn-xs join-item ${device === 'desktop' ? 'btn-primary' : 'btn-ghost'}`}><Monitor size={13} /></button>
            <button onClick={() => setDevice('mobile')} className={`btn btn-xs join-item ${device === 'mobile' ? 'btn-primary' : 'btn-ghost'}`}><Smartphone size={13} /></button>
          </div>
        </div>
        <div className="flex-1 overflow-auto bg-base-200 flex justify-center p-4">
          <iframe key={`${previewNonce}-${device}`} title="Theme preview" src={previewSrc}
            className="border border-base-300 bg-white shadow-sm"
            style={{ width: device === 'mobile' ? 390 : 1200, minHeight: 760, height: '100%', maxWidth: '100%' }} />
        </div>
        {cand.description && (
          <div className="px-4 py-2.5 border-t border-base-300 text-[12px] text-base-content/55 italic">{cand.description}</div>
        )}
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
          rows={2} placeholder="Refine it… “warmer”, “more contrast”, “quieter headings”"
          className="textarea textarea-bordered flex-1 text-[13px] resize-none leading-snug" />
        <button onClick={send} disabled={disabled || !text.trim()} className="btn btn-primary btn-sm btn-square">
          <Send className="h-3.5 w-3.5" />
        </button>
      </div>
      <div className="flex flex-wrap gap-1 mt-1.5">
        {['warmer', 'more contrast', 'quieter headings', 'rounder', 'airier'].map((q) => (
          <button key={q} onClick={() => !disabled && onSend(q)} disabled={disabled}
            className="text-[10px] px-2 py-0.5 border border-base-300 text-base-content/50 hover:text-primary hover:border-primary/40 disabled:opacity-40">
            {q}
          </button>
        ))}
      </div>
    </div>
  );
}
