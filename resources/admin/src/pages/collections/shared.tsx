import { useEffect } from 'react';
import { X } from 'lucide-react';
import type { Collection } from '@/lib/api';

export const apiErr = (e: any) =>
  e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

/** Flatten Laravel 422 errors ({field: [msg]}) into {field: msg}. */
export function validationErrors(e: any): Record<string, string> {
  const raw = e?.response?.data?.errors;
  if (!raw || typeof raw !== 'object') return {};
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(raw)) {
    out[k] = Array.isArray(v) ? String(v[0]) : String(v);
  }
  return out;
}

/** Modal shell following the AssetPicker daisyUI pattern; Esc closes. */
export function Modal({ open, onClose, title, children, maxW = 'max-w-lg' }: {
  open: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  maxW?: string;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <dialog className="modal modal-open" onClick={onClose}>
      <div className={`modal-box bg-base-100 border border-base-300/50 ${maxW}`} onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-medium text-base-content">{title}</h3>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square" aria-label="Close">
            <X size={14} />
          </button>
        </div>
        {children}
      </div>
      <form method="dialog" className="modal-backdrop"><button onClick={onClose}>close</button></form>
    </dialog>
  );
}

export const TIER_GUIDANCE: Record<Collection['tier'], { title: string; text: string }> = {
  static: {
    title: 'Static',
    text: 'Flat pages + client-side search. Best up to a few thousand records — works on any hosting.',
  },
  dynamic: {
    title: 'Dynamic',
    text: 'Live search API for large or fast-changing datasets. Needs VPS hosting.',
  },
};

/** Two radio cards with the tier guidance text (create dialog + schema editor). */
export function TierPicker({ value, onChange }: { value: Collection['tier']; onChange: (t: Collection['tier']) => void }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
      {(['static', 'dynamic'] as const).map((tier) => (
        <button
          key={tier}
          type="button"
          onClick={() => onChange(tier)}
          className={`text-left p-3 border rounded-box transition-colors ${
            value === tier
              ? 'border-primary bg-primary/10'
              : 'border-base-300/50 hover:border-base-300 hover:bg-base-300/10'
          }`}
        >
          <div className="flex items-center gap-2 mb-1">
            <span className={`w-3 h-3 rounded-full border-2 shrink-0 ${value === tier ? 'border-primary bg-primary' : 'border-base-content/25'}`} />
            <span className="text-[13px] font-medium text-base-content">{TIER_GUIDANCE[tier].title}</span>
          </div>
          <p className="text-[11px] text-base-content/45 leading-relaxed">{TIER_GUIDANCE[tier].text}</p>
        </button>
      ))}
    </div>
  );
}

export function TierBadge({ tier }: { tier: Collection['tier'] }) {
  return (
    <span className={`badge badge-sm badge-outline text-[11px] font-medium ${tier === 'dynamic' ? 'badge-info' : 'badge-ghost text-base-content/50'}`}>
      {tier}
    </span>
  );
}
