import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  BookOpen, Check, CheckCircle2, ExternalLink, Image as ImageIcon, Loader2, MessageSquare,
  RefreshCw, Sparkles, Wand2, X,
} from 'lucide-react';
import { useStudioStore } from './store';
import SpreadSketch from './SpreadSketch';
import { PATTERN_ALTERNATIVES } from './patternGeometry';
import type { SpreadRow } from './types';

/**
 * The spread generation loop: one spread at a time in flatplan order,
 * previewed through the REAL static preview endpoint (Blade in an iframe,
 * never a React approximation), then Keep / Revise / Rethink.
 */
export default function GenerationView() {
  const { siteId = '' } = useParams();
  const { session, generatingSpread, generateNextSpread, load, setAutoSourceImages } = useStudioStore();
  const [iframeLoading, setIframeLoading] = useState(true);

  const spreads = session?.spreads ?? [];
  const active = spreads.find((s) => s.status === 'generated' || s.status === 'revising') ?? null;
  const nextPending = spreads.find((s) => s.status === 'pending') ?? null;
  const approvedCount = spreads.filter((s) => s.status === 'approved').length;
  const complete = session?.status === 'complete';

  // Open the preview at the spread under review (its first page index = total
  // pages in all earlier spreads, since pages are laid out spread-by-spread) —
  // otherwise the flipbook always shows the cover and you can't see what you're
  // approving.
  const focusPage = useMemo(() => {
    const target = active ?? spreads.slice().reverse().find((s) => s.status === 'approved');
    if (!target) return 0;
    return spreads
      .filter((s) => s.position < target.position)
      .reduce((n, s) => n + (s.page_ids?.length ?? 0), 0);
  }, [active, spreads]);

  // reload the preview whenever the document changes OR the focused spread moves
  const previewKey = useMemo(
    () => `${session?.magazine_issue_id ?? ''}:${session?.updated_at ?? ''}:${spreads.map((s) => s.status).join(',')}:${focusPage}`,
    [session, spreads, focusPage],
  );

  if (!session) return null;

  if (complete) {
    return <CompletionSummary siteId={siteId} />;
  }

  return (
    <div className="flex flex-col h-full min-h-0">
      <div className="flex items-center gap-3 mb-3">
        <div className="flex-1 min-w-0">
          <h2 className="text-[15px] font-medium text-base-content/80">
            Making the magazine — {approvedCount} of {spreads.length} spreads kept
          </h2>
          <div className="flex gap-1 mt-1.5">
            {spreads.map((s) => (
              <span
                key={s.id}
                title={`${s.position === 0 ? 'Cover' : `Spread ${s.position}`}: ${s.working_title ?? ''} (${s.status})`}
                className={`h-1.5 flex-1 max-w-10 ${
                  s.status === 'approved' ? 'bg-success' : s.status === 'pending' ? 'bg-base-300' : 'bg-primary'
                }`}
              />
            ))}
          </div>
        </div>
        {session && (
          <button
            onClick={() => void setAutoSourceImages(!session.auto_source_images)}
            disabled={generatingSpread}
            title={session.auto_source_images
              ? 'Auto-finding stock photos for image slots is ON — click to turn off (empty slots then use the default image)'
              : 'Auto image search is OFF — image slots use the default image. Click to turn on'}
            className={`btn btn-sm text-[13px] gap-1.5 shrink-0 ${
              session.auto_source_images ? 'btn-primary btn-outline' : 'btn-ghost text-base-content/50'
            }`}
          >
            <ImageIcon className="h-3.5 w-3.5" />
            Auto-images {session.auto_source_images ? 'on' : 'off'}
          </button>
        )}
        {!active && nextPending && (
          <button
            onClick={() => void generateNextSpread()}
            disabled={generatingSpread}
            className="btn btn-primary btn-sm text-[13px] gap-1.5 shrink-0"
          >
            {generatingSpread ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Sparkles className="h-3.5 w-3.5" />}
            {generatingSpread
              ? 'Designing…'
              : nextPending.position === 0
                ? 'Design the cover'
                : `Design spread ${nextPending.position}`}
          </button>
        )}
        {/* Never-stuck guard: no spread awaiting a decision and none pending,
            yet the issue isn't marked complete (e.g. a missed status update on
            the last keep). Give an explicit way forward instead of a dead end. */}
        {!active && !nextPending && !complete && !generatingSpread && (
          <button
            onClick={() => void load(session.id)}
            className="btn btn-outline btn-sm text-[13px] gap-1.5 shrink-0"
            title="Reload the wizard state"
          >
            <RefreshCw className="h-3.5 w-3.5" /> Refresh
          </button>
        )}
      </div>

      <div className="flex-1 min-h-0 border border-base-300 relative bg-base-200/40">
        {session.magazine_issue_id ? (
          <>
            {(iframeLoading || generatingSpread) && (
              <div className="absolute inset-0 z-10 flex flex-col items-center justify-center bg-base-100/70 gap-2">
                <Loader2 className="h-6 w-6 animate-spin text-base-content/30" />
                <span className="text-[13px] text-base-content/45">
                  {generatingSpread ? 'The editorial director is designing…' : 'Rendering the real pages…'}
                </span>
              </div>
            )}
            <iframe
              key={previewKey}
              src={`/api/v1/sites/${siteId}/magazine-issues/${session.magazine_issue_id}/dtp-preview?page=${focusPage}`}
              title="Issue preview (real Blade render)"
              className="w-full h-full bg-white"
              onLoad={() => setIframeLoading(false)}
            />
          </>
        ) : (
          <div className="h-full flex flex-col items-center justify-center text-center px-8">
            {generatingSpread ? (
              <>
                <Loader2 className="h-6 w-6 animate-spin text-base-content/30 mb-3" />
                <p className="text-[14px] text-base-content/45">The editorial director is designing the cover…</p>
              </>
            ) : (
              <>
                <Wand2 className="h-10 w-10 text-base-content/10 mb-3" strokeWidth={1} />
                <p className="text-[14px] text-base-content/45 max-w-md">
                  The flatplan is locked. Hit the button above and I'll design the issue one spread at a
                  time — you approve each one before I move on.
                </p>
              </>
            )}
          </div>
        )}
      </div>

      {active && !generatingSpread && <DecisionBar spread={active} />}

      {/* Between spreads: the last one is kept and the next isn't designed yet.
          The 'Design spread N' button also sits top-right, but people look here
          (where Keep was) for the way forward — so make it obvious. */}
      {!active && nextPending && !generatingSpread && (
        <div className="border-t border-base-300 px-4 py-3 flex items-center justify-between gap-3 bg-base-100">
          <div className="text-[13px] text-base-content/60">
            {approvedCount > 0 ? (
              <><Check className="inline h-3.5 w-3.5 text-success mr-1" />
                {approvedCount} of {spreads.length} kept — {spreads.length - approvedCount} to go.</>
            ) : 'Ready when you are.'}
          </div>
          <button onClick={() => void generateNextSpread()} disabled={generatingSpread}
            className="btn btn-primary btn-sm gap-1.5">
            <Sparkles className="h-3.5 w-3.5" />
            {nextPending.position === 0 ? 'Design the cover' : `Design spread ${nextPending.position} →`}
          </button>
        </div>
      )}
    </div>
  );
}

function DecisionBar({ spread }: { spread: SpreadRow & { generation_notes?: Array<{ note: string }> } }) {
  const { keepSpread, reviseGeneratedSpread, rethinkSpread } = useStudioStore();
  const [mode, setMode] = useState<'idle' | 'revise' | 'rethink'>('idle');
  const [instruction, setInstruction] = useState('');

  const notes = (spread as SpreadRow & { generation_notes?: Array<{ note: string }> }).generation_notes ?? [];
  const lastNote = notes.length ? notes[notes.length - 1].note : '';
  const alternatives = (PATTERN_ALTERNATIVES[spread.pattern ?? ''] ?? []).slice(0, 3);

  return (
    <div className="border border-t-0 border-base-300 px-4 py-3">
      <div className="flex items-start gap-3">
        <div className="flex-1 min-w-0">
          <div className="text-[13px] font-medium text-base-content/75">
            {spread.position === 0 ? 'The cover' : `Spread ${spread.position}`} — {spread.working_title}
            <span className="ml-2 text-[11px] text-primary/70">{spread.pattern}</span>
          </div>
          {lastNote && (
            <p className="text-[13px] text-base-content/50 italic mt-0.5 leading-snug">“{lastNote}”</p>
          )}
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <button onClick={() => void keepSpread(spread.position)} className="btn btn-primary btn-sm text-[13px] gap-1.5">
            <Check className="h-3.5 w-3.5" /> Keep
          </button>
          <button
            onClick={() => setMode(mode === 'revise' ? 'idle' : 'revise')}
            className={`btn btn-sm text-[13px] gap-1.5 ${mode === 'revise' ? 'btn-neutral' : 'btn-ghost'}`}
          >
            <MessageSquare className="h-3.5 w-3.5" /> Revise
          </button>
          <button
            onClick={() => setMode(mode === 'rethink' ? 'idle' : 'rethink')}
            className={`btn btn-sm text-[13px] gap-1.5 ${mode === 'rethink' ? 'btn-neutral' : 'btn-ghost'}`}
          >
            <RefreshCw className="h-3.5 w-3.5" /> Rethink
          </button>
        </div>
      </div>

      {mode === 'revise' && (
        <div className="flex items-center gap-2 mt-2.5">
          <input
            autoFocus
            value={instruction}
            onChange={(e) => setInstruction(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && instruction.trim()) {
                void reviseGeneratedSpread(spread.position, instruction.trim());
                setMode('idle');
                setInstruction('');
              }
              if (e.key === 'Escape') setMode('idle');
            }}
            placeholder='Tell me what to change — "make the opening quieter", "bigger image, less text"…'
            className="input input-bordered input-sm flex-1 text-[13px] rounded-none"
          />
          <button
            onClick={() => {
              if (!instruction.trim()) return;
              void reviseGeneratedSpread(spread.position, instruction.trim());
              setMode('idle');
              setInstruction('');
            }}
            disabled={!instruction.trim()}
            className="btn btn-primary btn-sm text-[13px]"
          >
            Go
          </button>
          <button onClick={() => setMode('idle')} className="btn btn-ghost btn-sm px-1.5">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      {mode === 'rethink' && (
        <div className="flex items-center gap-2 mt-2.5 flex-wrap">
          <span className="text-[13px] text-base-content/45">Start over —</span>
          <button
            onClick={() => { void rethinkSpread(spread.position); setMode('idle'); }}
            className="btn btn-neutral btn-sm text-[13px]"
          >
            same pattern, new take
          </button>
          {alternatives.map((p) => (
            <button
              key={p}
              onClick={() => { void rethinkSpread(spread.position, p); setMode('idle'); }}
              className="btn btn-ghost btn-sm text-[13px] gap-1.5 border border-base-300"
              title={`Switch to ${p}`}
            >
              <SpreadSketch pattern={p} className="h-6 w-auto" /> {p}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function CompletionSummary({ siteId }: { siteId: string }) {
  const { session } = useStudioStore();
  if (!session) return null;

  const spreadCount = session.spreads?.length ?? 0;
  const pageCount = spreadCount > 0 ? 1 + (spreadCount - 1) * 2 : 0;

  return (
    <div className="flex flex-col items-center justify-center h-full text-center px-8">
      <CheckCircle2 className="h-12 w-12 text-success mb-4" strokeWidth={1.25} />
      <h2 className="text-lg font-medium text-base-content/85 mb-1">
        “{session.title || 'Your issue'}” is ready
      </h2>
      <p className="text-[14px] text-base-content/45 max-w-lg mb-1">
        {spreadCount} spreads, {pageCount} pages — every one approved by you. It's a normal
        magazine document now: open it in the editor to fine-tune anything by hand, then publish
        it the usual way.
      </p>
      <p className="text-[12px] text-base-content/30 mb-6">
        {(session.total_tokens / 1000).toFixed(1)}k AI tokens used across this session.
      </p>
      <div className="flex items-center gap-3">
        <Link
          to={`/sites/${siteId}/magazine-issues/${session.magazine_issue_id}/dtp-editor`}
          className="btn btn-primary btn-sm text-[14px] gap-1.5"
        >
          <BookOpen className="h-4 w-4" /> Open in Magazine editor
        </Link>
        <a
          href={`/api/v1/sites/${siteId}/magazine-issues/${session.magazine_issue_id}/dtp-preview`}
          target="_blank"
          rel="noreferrer"
          className="btn btn-ghost btn-sm text-[14px] gap-1.5"
        >
          <ExternalLink className="h-4 w-4" /> Full preview
        </a>
      </div>
    </div>
  );
}
