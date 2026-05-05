import { Check, AlertCircle } from 'lucide-react';
import type { WizardSession } from '../../types';

interface Props {
  session: WizardSession;
}

export default function ReviewArtifact({ session }: Props) {
  const brief = session.step1_brief;
  const structure = session.step2_structure;
  const analyses = session.step4_analyses || [];
  const directions = session.step5_directions || [];
  const thumbnails = session.step6_thumbnails || [];

  const articles = structure?.articles || [];
  const analyzedSlugs = new Set(analyses.map(a => a.article_slug));
  const directedSlugs = new Set(directions.map(d => d.article_slug));
  const thumbnailedSlugs = new Set(thumbnails.map(t => t.article_slug));

  return (
    <div className="space-y-4">
      {/* Brief summary */}
      {brief && (
        <div className="bg-base-200/30 rounded-lg p-3">
          <div className="text-[15px] text-base-content/30 uppercase tracking-wider mb-1">Issue Brief</div>
          <div className="text-[14px] text-base-content/70 italic">{brief.feeling}</div>
          <div className="text-[14px] text-base-content/40 mt-1">{brief.reader_state}</div>
          <div className="text-[14px] text-base-content/30 mt-1">{brief.page_count} pages</div>
        </div>
      )}

      {/* Per-article status */}
      <div>
        <div className="text-[15px] text-base-content/30 uppercase tracking-wider mb-2">Articles</div>
        <div className="space-y-1.5">
          {articles.map(a => {
            const hasAnalysis = analyzedSlugs.has(a.slug);
            const hasDirection = directedSlugs.has(a.slug);
            const hasThumbs = thumbnailedSlugs.has(a.slug);
            const isComplete = hasAnalysis && hasDirection && hasThumbs;

            return (
              <div key={a.slug} className="flex items-center gap-2 py-1.5 px-2 rounded bg-base-200/20">
                {isComplete ? (
                  <Check size={12} className="text-success shrink-0" />
                ) : (
                  <AlertCircle size={12} className="text-warning/50 shrink-0" />
                )}
                <div className="flex-1 min-w-0">
                  <div className="text-[15px] font-medium text-base-content/70 truncate">{a.title}</div>
                  <div className="flex gap-2 text-[15px] text-base-content/30">
                    <span>{a.pages}p · {a.rhythm}</span>
                    <span className={hasAnalysis ? 'text-success/60' : ''}>analysis</span>
                    <span className={hasDirection ? 'text-success/60' : ''}>direction</span>
                    <span className={hasThumbs ? 'text-success/60' : ''}>thumbnails</span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {articles.length === 0 && (
        <div className="text-[15px] text-base-content/25 text-center py-8">
          Complete earlier steps to see the review summary
        </div>
      )}
    </div>
  );
}
