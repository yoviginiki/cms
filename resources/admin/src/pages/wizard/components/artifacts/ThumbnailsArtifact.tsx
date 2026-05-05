import { Flag } from 'lucide-react';
import type { Step6Thumbnails, ThumbnailSpread } from '../../types';

interface Props {
  data: Step6Thumbnails | null;
  onChange: (data: Step6Thumbnails) => void;
  readOnly?: boolean;
}

export default function ThumbnailsArtifact({ data, onChange, readOnly }: Props) {
  const thumbs = data || { article_slug: '', spreads: [] };

  const toggleFlag = (idx: number) => {
    if (readOnly) return;
    const next = [...thumbs.spreads];
    next[idx] = { ...next[idx], flagged_for_revision: !next[idx].flagged_for_revision };
    onChange({ ...thumbs, spreads: next });
  };

  if (thumbs.spreads.length === 0) {
    return (
      <div className="text-[15px] text-base-content/25 text-center py-8">
        The AI will generate thumbnail wireframes here
      </div>
    );
  }

  return (
    <div>
      <div className="text-[14px] text-base-content/35 mb-3">
        Click a spread to flag it for revision
      </div>
      <div className="flex gap-3 overflow-x-auto pb-2">
        {thumbs.spreads.map((spread, idx) => (
          <div
            key={idx}
            onClick={() => toggleFlag(idx)}
            className={`shrink-0 w-48 border rounded-lg p-2 cursor-pointer transition-colors ${
              spread.flagged_for_revision
                ? 'border-warning/50 bg-warning/5'
                : 'border-base-300/20 bg-base-200/20 hover:bg-base-200/40'
            }`}
          >
            {/* Two-page spread representation */}
            <div className="flex gap-px mb-2">
              <div className="flex-1 h-28 bg-base-300/10 rounded-sm relative overflow-hidden">
                {spread.zones.filter((_, i) => i % 2 === 0).map((z, i) => (
                  <div key={i} className={`absolute ${
                    z.kind === 'image' ? 'bg-base-content/10' : 'bg-base-content/5'
                  }`} style={{
                    left: '10%', top: `${15 + i * 30}%`, width: '80%', height: '25%',
                  }}>
                    <span className="text-[7px] text-base-content/20 p-0.5 block truncate">{z.kind}</span>
                  </div>
                ))}
              </div>
              <div className="flex-1 h-28 bg-base-300/10 rounded-sm relative overflow-hidden">
                {spread.zones.filter((_, i) => i % 2 === 1).map((z, i) => (
                  <div key={i} className={`absolute ${
                    z.kind === 'image' ? 'bg-base-content/10' : 'bg-base-content/5'
                  }`} style={{
                    left: '10%', top: `${15 + i * 30}%`, width: '80%', height: '25%',
                  }}>
                    <span className="text-[7px] text-base-content/20 p-0.5 block truncate">{z.kind}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-[14px] font-mono text-base-content/30">S{spread.spread}</span>
              <span className="text-[15px] text-base-content/35">{spread.weight_position}</span>
              {spread.flagged_for_revision && (
                <Flag size={10} className="text-warning" />
              )}
            </div>
            <div className="text-[14px] text-base-content/25 mt-1 truncate">{spread.entry_exit}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
