import { useState, useCallback } from 'react';
import { ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-react';
import type { Step4Analysis } from '../../types';

interface Props {
  data: Step4Analysis | null;
  onChange: (data: Step4Analysis) => void;
  readOnly?: boolean;
}

const empty: Step4Analysis = {
  article_slug: '',
  voice: { tone: '', register: '', posture: '' },
  beats: [],
  spread_assignments: [],
};

// Section component OUTSIDE the main component to avoid re-creation
function Section({ id, title, open, onToggle, children }: {
  id: string; title: string; open: boolean; onToggle: (id: string) => void; children: React.ReactNode;
}) {
  return (
    <div className="border border-base-300/20 rounded-lg overflow-hidden">
      <button onClick={() => onToggle(id)} className="flex items-center gap-2 w-full px-3 py-2 bg-base-200/30 text-[15px] font-medium text-base-content/60">
        {open ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        {title}
      </button>
      {open && <div className="p-3 space-y-2">{children}</div>}
    </div>
  );
}

export default function AnalysisArtifact({ data, onChange, readOnly }: Props) {
  const analysis = data || empty;
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({ voice: true, beats: true, spreads: true });

  const toggle = useCallback((key: string) => setOpenSections(s => ({ ...s, [key]: !s[key] })), []);

  // Use a stable update function that merges into the current analysis
  const update = useCallback((patch: Partial<Step4Analysis>) => {
    onChange({ ...analysis, ...patch });
  }, [analysis, onChange]);

  // Voice field handler — stable per field
  const handleVoiceChange = useCallback((field: string, value: string) => {
    onChange({ ...analysis, voice: { ...analysis.voice, [field]: value } });
  }, [analysis, onChange]);

  // Beat field handlers
  const handleBeatChange = useCallback((idx: number, field: 'name' | 'description', value: string) => {
    const next = [...analysis.beats];
    next[idx] = { ...next[idx], [field]: value };
    onChange({ ...analysis, beats: next });
  }, [analysis, onChange]);

  const removeBeat = useCallback((idx: number) => {
    const next = [...analysis.beats];
    next.splice(idx, 1);
    onChange({ ...analysis, beats: next });
  }, [analysis, onChange]);

  const addBeat = useCallback(() => {
    onChange({ ...analysis, beats: [...analysis.beats, { name: '', description: '' }] });
  }, [analysis, onChange]);

  // Spread assignment handlers
  const handleSpreadChange = useCallback((idx: number, field: string, value: string | number) => {
    const next = [...analysis.spread_assignments];
    next[idx] = { ...next[idx], [field]: value };
    onChange({ ...analysis, spread_assignments: next });
  }, [analysis, onChange]);

  const removeSpread = useCallback((idx: number) => {
    const next = [...analysis.spread_assignments];
    next.splice(idx, 1);
    onChange({ ...analysis, spread_assignments: next });
  }, [analysis, onChange]);

  const addSpread = useCallback(() => {
    onChange({
      ...analysis,
      spread_assignments: [...analysis.spread_assignments, {
        spread: analysis.spread_assignments.length + 1, beat: '', role: '', density: '', tension: '',
      }],
    });
  }, [analysis, onChange]);

  return (
    <div className="space-y-3">
      {/* Voice */}
      <Section id="voice" title="Voice Diagnosis" open={openSections.voice} onToggle={toggle}>
        <div>
          <label className="text-[13px] text-base-content/35 uppercase tracking-wider mb-0.5 block">tone</label>
          <input
            value={analysis.voice.tone}
            onChange={e => handleVoiceChange('tone', e.target.value)}
            disabled={readOnly}
            className="input input-bordered input-sm w-full text-[14px]"
            placeholder="e.g. intimate, scholarly, urgent"
          />
        </div>
        <div>
          <label className="text-[13px] text-base-content/35 uppercase tracking-wider mb-0.5 block">register</label>
          <input
            value={analysis.voice.register}
            onChange={e => handleVoiceChange('register', e.target.value)}
            disabled={readOnly}
            className="input input-bordered input-sm w-full text-[14px]"
            placeholder="e.g. warm, cold, instructional"
          />
        </div>
        <div>
          <label className="text-[13px] text-base-content/35 uppercase tracking-wider mb-0.5 block">posture</label>
          <input
            value={analysis.voice.posture}
            onChange={e => handleVoiceChange('posture', e.target.value)}
            disabled={readOnly}
            className="input input-bordered input-sm w-full text-[14px]"
            placeholder="leaned-in / skimmed / contemplative"
          />
        </div>
      </Section>

      {/* Beats */}
      <Section id="beats" title={`Narrative Beats (${analysis.beats.length})`} open={openSections.beats} onToggle={toggle}>
        {analysis.beats.map((beat, i) => (
          <div key={i} className="flex items-start gap-2">
            <span className="text-[14px] text-base-content/25 font-mono w-4 pt-2 shrink-0">{i + 1}</span>
            <div className="flex-1 space-y-1">
              <input
                value={beat.name}
                onChange={e => handleBeatChange(i, 'name', e.target.value)}
                disabled={readOnly}
                placeholder="Beat name"
                className="input input-bordered input-sm w-full text-[14px] font-medium"
              />
              <input
                value={beat.description}
                onChange={e => handleBeatChange(i, 'description', e.target.value)}
                disabled={readOnly}
                placeholder="What happens in this beat"
                className="input input-bordered input-sm w-full text-[13px] text-base-content/50"
              />
            </div>
            {!readOnly && (
              <button onClick={() => removeBeat(i)}
                className="btn btn-ghost btn-sm btn-square text-base-content/20 hover:text-error mt-1">
                <Trash2 size={12} />
              </button>
            )}
          </div>
        ))}
        {!readOnly && (
          <button onClick={addBeat}
            className="btn btn-ghost btn-sm text-[13px] gap-1 text-base-content/30">
            <Plus size={12} /> Add beat
          </button>
        )}
      </Section>

      {/* Spread Assignments */}
      <Section id="spreads" title={`Spread Assignments (${analysis.spread_assignments.length})`} open={openSections.spreads} onToggle={toggle}>
        <div className="overflow-x-auto">
          <table className="table table-sm">
            <thead>
              <tr className="text-[11px] uppercase text-base-content/25">
                <th className="w-14">Spread</th>
                <th>Beat</th>
                <th>Role</th>
                <th>Density</th>
                <th>Tension</th>
                {!readOnly && <th className="w-10"></th>}
              </tr>
            </thead>
            <tbody>
              {analysis.spread_assignments.map((sa, i) => (
                <tr key={i}>
                  <td>
                    <input type="number" value={sa.spread}
                      onChange={e => handleSpreadChange(i, 'spread', +e.target.value)}
                      disabled={readOnly}
                      className="input input-bordered input-sm w-14 text-[13px]" />
                  </td>
                  <td>
                    <input value={sa.beat}
                      onChange={e => handleSpreadChange(i, 'beat', e.target.value)}
                      disabled={readOnly}
                      className="input input-bordered input-sm w-full text-[13px]" />
                  </td>
                  <td>
                    <select value={sa.role}
                      onChange={e => handleSpreadChange(i, 'role', e.target.value)}
                      disabled={readOnly}
                      className="select select-bordered select-sm w-full text-[13px]">
                      <option value="">—</option>
                      <option value="entry">Entry</option>
                      <option value="argument">Argument</option>
                      <option value="evidence">Evidence</option>
                      <option value="turn">Turn</option>
                      <option value="breath">Breath</option>
                      <option value="close">Close</option>
                    </select>
                  </td>
                  <td>
                    <select value={sa.density}
                      onChange={e => handleSpreadChange(i, 'density', e.target.value)}
                      disabled={readOnly}
                      className="select select-bordered select-sm w-full text-[13px]">
                      <option value="">—</option>
                      <option value="dense">Dense</option>
                      <option value="medium">Medium</option>
                      <option value="breath">Breath</option>
                    </select>
                  </td>
                  <td>
                    <select value={sa.tension}
                      onChange={e => handleSpreadChange(i, 'tension', e.target.value)}
                      disabled={readOnly}
                      className="select select-bordered select-sm w-full text-[13px]">
                      <option value="">—</option>
                      <option value="scale-vs-scale">Scale vs Scale</option>
                      <option value="density-vs-void">Density vs Void</option>
                      <option value="figure-vs-ground">Figure vs Ground</option>
                      <option value="ordered-vs-disrupted">Ordered vs Disrupted</option>
                    </select>
                  </td>
                  {!readOnly && (
                    <td>
                      <button onClick={() => removeSpread(i)}
                        className="btn btn-ghost btn-sm btn-square text-error/30 hover:text-error">
                        <Trash2 size={12} />
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!readOnly && (
          <button onClick={addSpread}
            className="btn btn-ghost btn-sm text-[13px] gap-1 text-base-content/30">
            <Plus size={12} /> Add spread
          </button>
        )}
      </Section>
    </div>
  );
}
