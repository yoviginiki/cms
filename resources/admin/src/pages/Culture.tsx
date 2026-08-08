import { Sparkles } from 'lucide-react';

/**
 * Culture module landing. Reached only when the culture-engine module resolves
 * enabled for the tenant and the user has `module.culture.use` (nav gates this).
 * Received bulletins are filed as ordinary drafts in the posts pipeline; this
 * view will list them (Phase 4 wires the received-drafts feed).
 */
export default function Culture() {
  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-6">
        <h1 className="text-xl font-bold text-base-content flex items-center gap-2">
          <Sparkles size={18} className="text-base-content/50" /> Culture
        </h1>
        <p className="text-[13px] text-base-content/50">
          AI-curated cultural bulletins received from the Culture Engine.
        </p>
      </div>

      <div className="flex flex-col items-center justify-center py-20 text-center">
        <Sparkles className="h-10 w-10 text-base-content/15 mb-4" strokeWidth={1.5} />
        <h3 className="text-sm font-medium text-base-content/60 mb-1">Received bulletins land here</h3>
        <p className="text-[13px] text-base-content/35 max-w-md">
          When the Culture Engine posts a weekly guide, it is filed as a <strong>draft</strong> —
          never auto-published. Review and publish it like any other post.
        </p>
      </div>
    </div>
  );
}
