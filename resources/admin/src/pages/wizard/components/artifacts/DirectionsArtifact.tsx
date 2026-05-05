import { useState } from 'react';
import { Check, Edit3 } from 'lucide-react';
import type { Step5Directions, DirectionProposal } from '../../types';

interface Props {
  data: Step5Directions | null;
  onChange: (data: Step5Directions) => void;
  readOnly?: boolean;
}

const empty: Step5Directions = { article_slug: '', proposed: [], chosen: null };

function FieldBlock({ label, value, onChange, disabled }: { label: string; value: string; onChange?: (v: string) => void; disabled?: boolean }) {
  return (
    <div>
      <div className="text-[15px] text-base-content/30 uppercase tracking-wider mb-0.5">{label}</div>
      {onChange && !disabled ? (
        <textarea value={value} onChange={e => onChange(e.target.value)}
          className="textarea textarea-bordered textarea-sm w-full text-[14px] min-h-[32px] leading-relaxed" rows={2} />
      ) : (
        <div className="text-[15px] text-base-content/70 leading-relaxed">{value || '—'}</div>
      )}
    </div>
  );
}

function ListField({ label, items, onChange, disabled }: { label: string; items: string[]; onChange?: (v: string[]) => void; disabled?: boolean }) {
  return (
    <div>
      <div className="text-[15px] text-base-content/30 uppercase tracking-wider mb-0.5">{label}</div>
      {onChange && !disabled ? (
        <textarea value={items.join('\n')} onChange={e => onChange(e.target.value.split('\n').filter(Boolean))}
          className="textarea textarea-bordered textarea-sm w-full text-[14px] min-h-[48px]" rows={3} />
      ) : (
        <ul className="space-y-0.5">
          {items.map((item, i) => (
            <li key={i} className="text-[14px] text-base-content/60 flex items-start gap-1">
              <span className="text-base-content/20 mt-0.5">·</span>{item}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default function DirectionsArtifact({ data, onChange, readOnly }: Props) {
  const dirs = data || empty;
  const [editing, setEditing] = useState(false);

  const choose = (idx: number) => {
    onChange({ ...dirs, chosen: { ...dirs.proposed[idx] } });
  };

  const updateChosen = (patch: Partial<DirectionProposal>) => {
    if (!dirs.chosen) return;
    onChange({ ...dirs, chosen: { ...dirs.chosen, ...patch } });
  };

  if (dirs.proposed.length === 0) {
    return (
      <div className="text-[15px] text-base-content/25 text-center py-8">
        The AI will propose 3 design directions here
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Direction cards */}
      <div className={`grid gap-3 ${dirs.proposed.length <= 3 ? 'grid-cols-1 lg:grid-cols-3' : 'grid-cols-1'}`}>
        {dirs.proposed.map((dir, idx) => {
          const isChosen = dirs.chosen?.name === dir.name;
          return (
            <div key={idx} className={`border rounded-xl p-3 transition-colors ${
              isChosen ? 'border-primary/40 bg-primary/5' : 'border-base-300/20 bg-base-200/20'
            }`}>
              {/* Header with choose button */}
              <div className="flex items-start justify-between mb-2">
                <div>
                  <h4 className="text-[15px] font-semibold text-base-content/80">{dir.name}</h4>
                  <p className="text-[14px] text-base-content/50 italic mt-0.5">{dir.thesis}</p>
                </div>
                {!readOnly && (
                  <button onClick={() => choose(idx)}
                    className={`btn btn-sm gap-1 ${isChosen ? 'btn-primary' : 'btn-ghost'}`}>
                    {isChosen ? <><Check size={10} /> Chosen</> : 'Choose'}
                  </button>
                )}
              </div>

              {/* References */}
              <div className="text-[15px] text-base-content/35 mb-2">
                {dir.references.join(' · ')}
              </div>

              {/* Sections */}
              <div className="space-y-2 border-t border-base-300/15 pt-2">
                <div className="grid grid-cols-2 gap-2">
                  <FieldBlock label="Display type" value={dir.typography.display} />
                  <FieldBlock label="Text type" value={dir.typography.text} />
                </div>
                <FieldBlock label="Scale ratio" value={dir.typography.scale_ratio} />
                <FieldBlock label="Signature move" value={dir.typography.signature_move} />

                <div className="border-t border-base-300/10 pt-2">
                  <FieldBlock label="Grid" value={`${dir.grid.columns} · ${dir.grid.baseline}`} />
                  <FieldBlock label="Grid breaks" value={`${dir.grid.breaks} — ${dir.grid.break_meaning}`} />
                </div>

                <div className="border-t border-base-300/10 pt-2">
                  <FieldBlock label="Image strategy" value={`${dir.image_strategy.treatment} · ${dir.image_strategy.ratio}`} />
                </div>

                <div className="border-t border-base-300/10 pt-2 grid grid-cols-2 gap-2">
                  <ListField label="Rules" items={dir.rules} />
                  <ListField label="Banned" items={dir.banned_moves} />
                </div>

                <FieldBlock label="Spread relationship" value={dir.spread_relationship} />
              </div>
            </div>
          );
        })}
      </div>

      {/* Chosen direction editable view */}
      {dirs.chosen && !readOnly && (
        <div className="border-t border-base-300/20 pt-4">
          <div className="flex items-center justify-between mb-3">
            <h4 className="text-[15px] font-medium text-base-content/60 uppercase tracking-wider">
              Chosen: {dirs.chosen.name}
            </h4>
            <button onClick={() => setEditing(!editing)} className="btn btn-ghost btn-sm gap-1 text-[14px]">
              <Edit3 size={10} /> {editing ? 'Done editing' : 'Edit inline'}
            </button>
          </div>

          {editing && (
            <div className="space-y-3 bg-base-200/30 rounded-lg p-3">
              <FieldBlock label="Thesis" value={dirs.chosen.thesis} onChange={v => updateChosen({ thesis: v })} />

              <div className="grid grid-cols-2 gap-2">
                <FieldBlock label="Display type" value={dirs.chosen.typography.display}
                  onChange={v => updateChosen({ typography: { ...dirs.chosen!.typography, display: v } })} />
                <FieldBlock label="Text type" value={dirs.chosen.typography.text}
                  onChange={v => updateChosen({ typography: { ...dirs.chosen!.typography, text: v } })} />
              </div>

              <FieldBlock label="Signature move" value={dirs.chosen.typography.signature_move}
                onChange={v => updateChosen({ typography: { ...dirs.chosen!.typography, signature_move: v } })} />
              <FieldBlock label="Grid columns" value={dirs.chosen.grid.columns}
                onChange={v => updateChosen({ grid: { ...dirs.chosen!.grid, columns: v } })} />
              <FieldBlock label="Image treatment" value={dirs.chosen.image_strategy.treatment}
                onChange={v => updateChosen({ image_strategy: { ...dirs.chosen!.image_strategy, treatment: v } })} />

              <ListField label="Rules" items={dirs.chosen.rules}
                onChange={v => updateChosen({ rules: v })} />
              <ListField label="Banned moves" items={dirs.chosen.banned_moves}
                onChange={v => updateChosen({ banned_moves: v })} />
              <FieldBlock label="Spread relationship" value={dirs.chosen.spread_relationship}
                onChange={v => updateChosen({ spread_relationship: v })} />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
