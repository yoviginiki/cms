import type { Step1Brief } from '../../types';

interface Props {
  data: Step1Brief | null;
  onChange: (data: Step1Brief) => void;
  readOnly?: boolean;
}

const empty: Step1Brief = { feeling: '', reader_state: '', anchors: [], page_count: 24 };

export default function BriefArtifact({ data, onChange, readOnly }: Props) {
  const brief = data || empty;

  const update = (patch: Partial<Step1Brief>) => onChange({ ...brief, ...patch });

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[14px] text-base-content/40 uppercase tracking-wider mb-1 block">Feeling</label>
        <textarea
          value={brief.feeling}
          onChange={e => update({ feeling: e.target.value })}
          disabled={readOnly}
          placeholder="What sensation lingers when someone finishes this issue?"
          className="textarea textarea-bordered textarea-sm w-full h-16 text-[14px]"
        />
      </div>
      <div>
        <label className="text-[14px] text-base-content/40 uppercase tracking-wider mb-1 block">Reader State</label>
        <textarea
          value={brief.reader_state}
          onChange={e => update({ reader_state: e.target.value })}
          disabled={readOnly}
          placeholder="Who is the reader and what state are they in?"
          className="textarea textarea-bordered textarea-sm w-full h-16 text-[14px]"
        />
      </div>
      <div>
        <label className="text-[14px] text-base-content/40 uppercase tracking-wider mb-1 block">Anchors</label>
        <input
          type="text"
          value={brief.anchors.join(', ')}
          onChange={e => update({ anchors: e.target.value.split(',').map(s => s.trim()).filter(Boolean) })}
          disabled={readOnly}
          placeholder="Comma-separated: cover story, must-run pieces..."
          className="input input-bordered input-sm w-full text-[14px]"
        />
      </div>
      <div>
        <label className="text-[14px] text-base-content/40 uppercase tracking-wider mb-1 block">Page Count</label>
        <input
          type="number"
          value={brief.page_count}
          onChange={e => update({ page_count: Number(e.target.value) || 24 })}
          disabled={readOnly}
          min={8} max={120} step={2}
          className="input input-bordered input-sm w-24 text-[14px]"
        />
      </div>
    </div>
  );
}
