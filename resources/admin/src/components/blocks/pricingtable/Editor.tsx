import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface Plan {
  name: string;
  price: string;
  period: string;
  features: string[];
  ctaText: string;
  ctaUrl: string;
  highlighted: boolean;
}

export const PricingtableEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { plans: Plan[]; columns: number };
  const plans = data.plans || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updatePlan = (index: number, key: keyof Plan, value: unknown) => {
    const updated = plans.map((p, i) => (i === index ? { ...p, [key]: value } : p));
    update('plans', updated);
  };

  const addPlan = () => {
    update('plans', [
      ...plans,
      { name: 'Plan', price: '$0', period: '/mo', features: ['Feature'], ctaText: 'Choose', ctaUrl: '#', highlighted: false },
    ]);
  };

  const removePlan = (index: number) => {
    if (plans.length <= 1) return;
    update('plans', plans.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Columns</label>
        <select
          className="select select-bordered select-sm w-full"
          value={data.columns || 3}
          onChange={(e) => update('columns', Number(e.target.value))}
        >
          <option value={1}>1</option>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={4}>4</option>
        </select>
      </div>

      {plans.map((plan, i) => (
        <div key={i} className="rounded border border-gray-200 p-3 space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-gray-500 uppercase">Plan {i + 1}</span>
            <button type="button" onClick={() => removePlan(i)} disabled={plans.length <= 1} className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300">Remove</button>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Name</label>
              <input type="text" className="input input-bordered input-sm w-full" value={plan.name} onChange={(e) => updatePlan(i, 'name', e.target.value)} />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Price</label>
              <input type="text" className="input input-bordered input-sm w-full" value={plan.price} onChange={(e) => updatePlan(i, 'price', e.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Period</label>
              <input type="text" className="input input-bordered input-sm w-full" value={plan.period} onChange={(e) => updatePlan(i, 'period', e.target.value)} />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">CTA Text</label>
              <input type="text" className="input input-bordered input-sm w-full" value={plan.ctaText} onChange={(e) => updatePlan(i, 'ctaText', e.target.value)} />
            </div>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">CTA URL</label>
            <input type="text" className="input input-bordered input-sm w-full" value={plan.ctaUrl} onChange={(e) => updatePlan(i, 'ctaUrl', e.target.value)} />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Features (one per line)</label>
            <textarea
              className="textarea textarea-bordered textarea-sm w-full"
              rows={3}
              value={plan.features.join('\n')}
              onChange={(e) => updatePlan(i, 'features', e.target.value.split('\n'))}
            />
          </div>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="checkbox checkbox-sm" checked={!!plan.highlighted} onChange={(e) => updatePlan(i, 'highlighted', e.target.checked)} />
            <span className="text-[11px] text-base-content/50">Highlighted</span>
          </label>
        </div>
      ))}

      <button type="button" onClick={addPlan} className="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Plan</button>
    </div>
  );
};
