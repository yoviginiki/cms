import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface Feature {
  text: string;
  included: boolean;
}

export const PricingcardEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    planName: string;
    price: string;
    period: string;
    features: Feature[];
    ctaText: string;
    ctaUrl: string;
    highlighted: boolean;
    badge: string;
  };

  const features = data.features || [];

  const update = (key: string, value: unknown) => {
    onUpdate({ ...block.data, [key]: value });
  };

  const updateFeature = (index: number, key: keyof Feature, value: string | boolean) => {
    const updated = features.map((f, i) =>
      i === index ? { ...f, [key]: value } : f,
    );
    update('features', updated);
  };

  const addFeature = () => {
    update('features', [...features, { text: 'New feature', included: true }]);
  };

  const removeFeature = (index: number) => {
    update('features', features.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Plan Name</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.planName || ''}
          onChange={(e) => update('planName', e.target.value)}
        />
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Price</label>
          <input
            type="text"
            className="input input-bordered input-sm w-full"
            value={data.price || ''}
            onChange={(e) => update('price', e.target.value)}
          />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Period</label>
          <select
            className="select select-bordered select-sm w-full"
            value={data.period || 'month'}
            onChange={(e) => update('period', e.target.value)}
          >
            <option value="month">Month</option>
            <option value="year">Year</option>
            <option value="week">Week</option>
            <option value="one-time">One-time</option>
          </select>
        </div>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Badge</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.badge || ''}
          onChange={(e) => update('badge', e.target.value)}
          placeholder="e.g. Most Popular"
        />
      </div>
      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          className="checkbox checkbox-sm"
          checked={!!data.highlighted}
          onChange={(e) => update('highlighted', e.target.checked)}
        />
        <label className="text-[11px] text-base-content/50">Highlighted</label>
      </div>

      <div>
        <label className="text-[11px] text-base-content/50 mb-2 block">Features</label>
        {features.map((feat, index) => (
          <div key={index} className="flex items-center gap-2 mb-2">
            <input
              type="checkbox"
              className="checkbox checkbox-sm"
              checked={!!feat.included}
              onChange={(e) => updateFeature(index, 'included', e.target.checked)}
              title="Included"
            />
            <input
              type="text"
              className="input input-bordered input-sm flex-1"
              value={feat.text}
              onChange={(e) => updateFeature(index, 'text', e.target.value)}
            />
            <button
              type="button"
              onClick={() => removeFeature(index)}
              className="text-xs text-red-600 hover:text-red-800"
            >
              Remove
            </button>
          </div>
        ))}
        <button
          type="button"
          onClick={addFeature}
          className="text-sm text-blue-600 hover:text-blue-800 font-medium"
        >
          + Add Feature
        </button>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">CTA Text</label>
          <input
            type="text"
            className="input input-bordered input-sm w-full"
            value={data.ctaText || ''}
            onChange={(e) => update('ctaText', e.target.value)}
          />
        </div>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">CTA URL</label>
          <input
            type="text"
            className="input input-bordered input-sm w-full"
            value={data.ctaUrl || ''}
            onChange={(e) => update('ctaUrl', e.target.value)}
          />
        </div>
      </div>
    </div>
  );
};
