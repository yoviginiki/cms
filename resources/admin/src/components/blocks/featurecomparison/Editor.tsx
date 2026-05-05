import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface Plan {
  name: string;
  price: string;
}

interface Feature {
  name: string;
  values: (boolean | string)[];
}

export const FeaturecomparisonEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    plans: Plan[];
    features: Feature[];
  };

  const plans = data.plans || [];
  const features = data.features || [];

  const update = (key: string, value: unknown) => {
    onUpdate({ ...block.data, [key]: value });
  };

  const updatePlan = (index: number, key: keyof Plan, value: string) => {
    const updated = plans.map((p, i) => (i === index ? { ...p, [key]: value } : p));
    update('plans', updated);
  };

  const addPlan = () => {
    const newPlans = [...plans, { name: 'New Plan', price: '$0' }];
    const newFeatures = features.map((f) => ({
      ...f,
      values: [...f.values, false],
    }));
    onUpdate({ ...block.data, plans: newPlans, features: newFeatures });
  };

  const removePlan = (index: number) => {
    if (plans.length <= 1) return;
    const newPlans = plans.filter((_, i) => i !== index);
    const newFeatures = features.map((f) => ({
      ...f,
      values: f.values.filter((_, i) => i !== index),
    }));
    onUpdate({ ...block.data, plans: newPlans, features: newFeatures });
  };

  const updateFeatureName = (index: number, name: string) => {
    const updated = features.map((f, i) => (i === index ? { ...f, name } : f));
    update('features', updated);
  };

  const updateFeatureValue = (fi: number, pi: number, value: boolean | string) => {
    const updated = features.map((f, i) => {
      if (i !== fi) return f;
      const newValues = [...f.values];
      newValues[pi] = value;
      return { ...f, values: newValues };
    });
    update('features', updated);
  };

  const addFeature = () => {
    const newFeature: Feature = { name: 'New Feature', values: plans.map(() => false) };
    update('features', [...features, newFeature]);
  };

  const removeFeature = (index: number) => {
    update('features', features.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-2 block">Plans</label>
        {plans.map((plan, index) => (
          <div key={index} className="flex items-center gap-2 mb-2">
            <input
              type="text"
              className="input input-bordered input-sm flex-1"
              value={plan.name}
              onChange={(e) => updatePlan(index, 'name', e.target.value)}
              placeholder="Plan name"
            />
            <input
              type="text"
              className="input input-bordered input-sm w-24"
              value={plan.price}
              onChange={(e) => updatePlan(index, 'price', e.target.value)}
              placeholder="Price"
            />
            <button
              type="button"
              onClick={() => removePlan(index)}
              disabled={plans.length <= 1}
              className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300 disabled:cursor-not-allowed"
            >
              Remove
            </button>
          </div>
        ))}
        <button
          type="button"
          onClick={addPlan}
          className="text-sm text-blue-600 hover:text-blue-800 font-medium"
        >
          + Add Plan
        </button>
      </div>

      <div>
        <label className="text-[11px] text-base-content/50 mb-2 block">Features</label>
        {features.map((feat, fi) => (
          <div key={fi} className="rounded border border-gray-200 p-3 mb-2 space-y-2">
            <div className="flex items-center justify-between">
              <input
                type="text"
                className="input input-bordered input-sm flex-1 mr-2"
                value={feat.name}
                onChange={(e) => updateFeatureName(fi, e.target.value)}
                placeholder="Feature name"
              />
              <button
                type="button"
                onClick={() => removeFeature(fi)}
                className="text-xs text-red-600 hover:text-red-800"
              >
                Remove
              </button>
            </div>
            <div className="flex items-center gap-3 flex-wrap">
              {plans.map((plan, pi) => {
                const val = feat.values[pi];
                const isBool = typeof val === 'boolean';
                return (
                  <div key={pi} className="flex items-center gap-1">
                    <input
                      type="checkbox"
                      className="checkbox checkbox-sm"
                      checked={isBool ? val : !!val}
                      onChange={(e) => updateFeatureValue(fi, pi, e.target.checked)}
                    />
                    <span className="text-[11px] text-base-content/50">{plan.name}</span>
                  </div>
                );
              })}
            </div>
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
    </div>
  );
};
