import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';

/**
 * Scene-based animation editor for slider layers (IN / LOOP / OUT), bound to
 * block.data.animation. Every option mirrors the backend allowlists in
 * App\Support\Blocks\SliderAnimation and the motion-runtime preset table —
 * nothing settable here can be rejected by the sanitizer.
 */

const PRESETS_IN = ['fadeUp', 'fadeIn', 'slideLeft', 'slideRight', 'zoomIn', 'maskWipe'] as const;
const PRESETS_OUT = ['fadeUp-out', 'fadeOut', 'slideLeft-out', 'slideRight-out'] as const;
const ATTRS = ['x', 'y', 'scale', 'rotation', 'autoAlpha', 'opacity', 'clipPath'] as const;
const EASES = [
  'power1.out', 'power2.out', 'power3.out', 'power4.out',
  'power1.in', 'power2.in', 'power3.in',
  'power1.inOut', 'power2.inOut', 'power3.inOut',
  'sine.inOut', 'sine.out', 'expo.out', 'circ.out', 'back.out(1.6)', 'none', 'linear',
] as const;
const SPLITS = ['none', 'chars', 'words', 'lines'] as const;

interface Track {
  attr: string; from?: string; to?: string;
  delay?: number; duration?: number; ease?: string; yoyo?: boolean; repeat?: number;
}
interface Scene { preset?: string; delay?: number; duration?: number; stagger?: number; tracks?: Track[] }
export interface LayerAnimation {
  split?: string;
  in?: Scene | null; loop?: Scene | null; out?: Scene | null;
  trigger?: { action: string; target: string } | null;
}

interface Props {
  value: LayerAnimation;
  onChange: (v: LayerAnimation) => void;
}

function Num({ label, value, onChange, min, max, step = 0.05, placeholder }: {
  label: string; value: number | undefined; onChange: (v: number | undefined) => void;
  min: number; max: number; step?: number; placeholder?: string;
}) {
  return (
    <div>
      <label className="text-[9px] text-base-content/40">{label}</label>
      <input type="number" min={min} max={max} step={step} value={value ?? ''} placeholder={placeholder}
        onChange={e => {
          const n = Number(e.target.value);
          onChange(e.target.value === '' || !Number.isFinite(n) ? undefined : Math.max(min, Math.min(max, n)));
        }}
        className="input input-bordered input-xs w-full text-[10px]" />
    </div>
  );
}

function SceneEditor({ scene, phase, onChange }: {
  scene: Scene | null | undefined;
  phase: 'in' | 'loop' | 'out';
  onChange: (s: Scene | null) => void;
}) {
  const presets = phase === 'out' ? PRESETS_OUT : PRESETS_IN;
  const mode: 'none' | 'preset' | 'tracks' = !scene ? 'none' : (scene.tracks?.length ? 'tracks' : 'preset');
  const s = scene ?? {};

  const setMode = (m: 'none' | 'preset' | 'tracks') => {
    if (m === 'none') onChange(null);
    else if (m === 'preset') onChange({ preset: phase === 'out' ? 'fadeOut' : 'fadeUp', duration: 0.6, delay: s.delay ?? 0 });
    else onChange({ tracks: [{ attr: phase === 'loop' ? 'y' : 'x', from: '0', to: phase === 'loop' ? '-10' : '0', duration: phase === 'loop' ? 2.5 : 0.6, ease: phase === 'loop' ? 'sine.inOut' : 'power3.out', ...(phase === 'loop' ? { yoyo: true, repeat: -1 } : {}) }] });
  };

  const updateTrack = (i: number, patch: Partial<Track>) => {
    const tracks = [...(s.tracks ?? [])];
    tracks[i] = { ...tracks[i], ...patch };
    onChange({ ...s, tracks });
  };

  return (
    <div className="border border-base-300/40 p-2 space-y-1.5">
      <div className="flex items-center justify-between">
        <span className="text-[10px] font-semibold uppercase tracking-wider text-base-content/50">{phase}</span>
        <div className="join">
          {(['none', 'preset', 'tracks'] as const).map(m => (
            <button key={m} type="button" onClick={() => setMode(m)}
              className={`join-item btn btn-xs text-[9px] ${mode === m ? 'btn-primary' : 'btn-ghost'}`}
              disabled={phase === 'loop' && m === 'preset'}>
              {m}
            </button>
          ))}
        </div>
      </div>

      {mode === 'preset' && (
        <>
          <select value={s.preset ?? ''} onChange={e => onChange({ ...s, preset: e.target.value })}
            className="select select-bordered select-xs w-full text-[10px]">
            {presets.map(p => <option key={p} value={p}>{p}</option>)}
          </select>
          <div className="grid grid-cols-3 gap-1">
            <Num label="Delay s" value={s.delay} onChange={v => onChange({ ...s, delay: v })} min={0} max={10} />
            <Num label="Duration s" value={s.duration} onChange={v => onChange({ ...s, duration: v })} min={0.05} max={10} />
            <Num label="Stagger s" value={s.stagger} onChange={v => onChange({ ...s, stagger: v })} min={0} max={1} step={0.01} placeholder="split only" />
          </div>
        </>
      )}

      {mode === 'tracks' && (
        <div className="space-y-1.5">
          {(s.tracks ?? []).map((tr, i) => (
            <div key={i} className="bg-base-200/50 p-1.5 space-y-1">
              <div className="flex items-center gap-1">
                <select value={tr.attr} onChange={e => updateTrack(i, { attr: e.target.value })}
                  className="select select-bordered select-xs text-[10px] flex-1">
                  {ATTRS.map(a => <option key={a} value={a}>{a}</option>)}
                </select>
                <select value={tr.ease ?? 'power2.out'} onChange={e => updateTrack(i, { ease: e.target.value })}
                  className="select select-bordered select-xs text-[10px] flex-1">
                  {EASES.map(e2 => <option key={e2} value={e2}>{e2}</option>)}
                </select>
                <button type="button" className="p-1 text-base-content/30 hover:text-red-500"
                  onClick={() => onChange({ ...s, tracks: (s.tracks ?? []).filter((_, j) => j !== i) })}>
                  <Trash2 size={11} />
                </button>
              </div>
              <div className="grid grid-cols-4 gap-1">
                <div>
                  <label className="text-[9px] text-base-content/40">From</label>
                  <input value={tr.from ?? ''} onChange={e => updateTrack(i, { from: e.target.value })}
                    className="input input-bordered input-xs w-full text-[10px]" placeholder="-100%" />
                </div>
                <div>
                  <label className="text-[9px] text-base-content/40">To</label>
                  <input value={tr.to ?? ''} onChange={e => updateTrack(i, { to: e.target.value })}
                    className="input input-bordered input-xs w-full text-[10px]" placeholder="0%" />
                </div>
                <Num label="Delay" value={tr.delay} onChange={v => updateTrack(i, { delay: v })} min={0} max={10} />
                <Num label="Dur" value={tr.duration} onChange={v => updateTrack(i, { duration: v })} min={0.05} max={10} />
              </div>
              {phase === 'loop' && (
                <div className="flex items-center gap-3 text-[10px] text-base-content/50">
                  <label className="flex items-center gap-1 cursor-pointer">
                    <input type="checkbox" className="checkbox checkbox-xs" checked={tr.yoyo !== false}
                      onChange={e => updateTrack(i, { yoyo: e.target.checked })} /> yoyo
                  </label>
                  <label className="flex items-center gap-1">
                    repeat
                    <input type="number" min={-1} max={20} value={tr.repeat ?? -1}
                      onChange={e => updateTrack(i, { repeat: Number(e.target.value) })}
                      className="input input-bordered input-xs w-14 text-[10px]" />
                    <span className="text-base-content/30">(-1 = ∞)</span>
                  </label>
                </div>
              )}
            </div>
          ))}
          {(s.tracks ?? []).length < 8 && (
            <button type="button"
              onClick={() => onChange({ ...s, tracks: [...(s.tracks ?? []), { attr: 'x', from: '0', to: '0', duration: 0.6, ease: 'power2.out' }] })}
              className="btn btn-ghost btn-xs gap-1 text-[10px]"><Plus size={11} /> Add track</button>
          )}
        </div>
      )}
    </div>
  );
}

export function AnimationScenePanel({ value, onChange }: Props) {
  const [showTrigger, setShowTrigger] = useState(!!value.trigger);

  return (
    <div className="space-y-2">
      <div>
        <label className="text-[10px] text-base-content/40">Split text</label>
        <select value={value.split ?? 'none'}
          onChange={e => onChange({ ...value, split: e.target.value === 'none' ? undefined : e.target.value })}
          className="select select-bordered select-xs w-full text-[11px]">
          {SPLITS.map(s => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      <SceneEditor phase="in" scene={value.in} onChange={s => onChange({ ...value, in: s ?? undefined })} />
      <SceneEditor phase="loop" scene={value.loop} onChange={s => onChange({ ...value, loop: s ?? undefined })} />
      <SceneEditor phase="out" scene={value.out} onChange={s => onChange({ ...value, out: s ?? undefined })} />

      <div className="border-t border-base-300/20 pt-1.5">
        <label className="flex items-center justify-between text-[11px] text-base-content/50 cursor-pointer">
          Click trigger
          <input type="checkbox" className="toggle toggle-xs" checked={showTrigger}
            onChange={e => { setShowTrigger(e.target.checked); if (!e.target.checked) onChange({ ...value, trigger: undefined }); }} />
        </label>
        {showTrigger && (
          <div className="grid grid-cols-2 gap-1.5 mt-1">
            <select value={value.trigger?.action ?? 'link'}
              onChange={e => onChange({ ...value, trigger: { action: e.target.value, target: value.trigger?.target ?? '' } })}
              className="select select-bordered select-xs text-[10px]">
              <option value="link">Open link</option>
              <option value="goToSlide">Go to slide</option>
            </select>
            <input value={value.trigger?.target ?? ''}
              onChange={e => onChange({ ...value, trigger: { action: value.trigger?.action ?? 'link', target: e.target.value } })}
              className="input input-bordered input-xs text-[10px]"
              placeholder={value.trigger?.action === 'goToSlide' ? 'slide # (0-based)' : '/contact'} />
          </div>
        )}
      </div>
    </div>
  );
}
