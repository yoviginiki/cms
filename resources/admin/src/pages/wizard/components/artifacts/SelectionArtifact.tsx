import type { Step3Selection, Step2Structure } from '../../types';

interface Props {
  data: Step3Selection | null;
  structure: Step2Structure | null;
  onChange: (data: Step3Selection) => void;
  readOnly?: boolean;
}

export default function SelectionArtifact({ data, structure, onChange, readOnly }: Props) {
  const articles = structure?.articles || [];
  const selected = data?.selected_slug || '';

  return (
    <div className="space-y-1">
      <div className="text-[14px] text-base-content/40 mb-2">Select an article to design next</div>
      {articles.length === 0 ? (
        <div className="text-[15px] text-base-content/25 text-center py-4">
          Lock the Structure step first to see articles here
        </div>
      ) : (
        articles.map(a => (
          <label
            key={a.slug}
            className={`flex items-center gap-3 p-2.5 rounded-lg cursor-pointer transition-colors border ${
              selected === a.slug
                ? 'bg-primary/10 border-primary/20'
                : 'bg-base-200/30 border-transparent hover:bg-base-200/50'
            } ${readOnly ? 'pointer-events-none' : ''}`}
          >
            <input
              type="radio"
              name="article-selection"
              className="radio radio-xs radio-primary"
              checked={selected === a.slug}
              onChange={() => onChange({ selected_slug: a.slug })}
              disabled={readOnly}
            />
            <div className="flex-1 min-w-0">
              <div className="text-[14px] font-medium text-base-content/80 truncate">{a.title}</div>
              <div className="text-[14px] text-base-content/35">{a.pages}p · {a.rhythm} · {a.role}</div>
            </div>
          </label>
        ))
      )}
    </div>
  );
}
