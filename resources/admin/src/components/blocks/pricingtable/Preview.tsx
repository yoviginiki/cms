import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface Plan {
  name: string;
  price: string;
  period: string;
  features: string[];
  ctaText: string;
  ctaUrl: string;
  highlighted: boolean;
}

export const PricingtablePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { plans: Plan[]; columns: number };
  const plans = data.plans || [];
  const cols = data.columns || 3;

  return (
    <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
      {(plans || []).map((plan, i) => (
        <div
          key={i}
          className={`rounded-lg border p-4 text-center ${plan.highlighted ? 'border-blue-500 shadow-md' : 'border-gray-200'}`}
        >
          <div className="font-semibold text-sm mb-1">{plan.name}</div>
          <div className="text-2xl font-bold">
            {plan.price}
            <span className="text-xs font-normal text-gray-500">{plan.period}</span>
          </div>
          <ul className="text-xs text-gray-600 mt-2 space-y-1">
            {(plan.features || []).map((f, fi) => (
              <li key={fi}>{f}</li>
            ))}
          </ul>
          <div className="mt-3">
            <span className="inline-block bg-blue-600 text-white text-xs px-3 py-1 rounded">
              {plan.ctaText}
            </span>
          </div>
        </div>
      ))}
    </div>
  );
};
