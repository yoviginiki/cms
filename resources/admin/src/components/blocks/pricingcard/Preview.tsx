import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface Feature {
  text: string;
  included: boolean;
}

export const PricingcardPreview: React.FC<BlockComponentProps> = ({ block }) => {
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

  return (
    <div className={`rounded-lg border-2 p-5 text-center relative ${data.highlighted ? 'border-blue-500 shadow-lg' : 'border-gray-200'}`}>
      {data.badge && (
        <span className="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs px-3 py-0.5 rounded-full">
          {data.badge}
        </span>
      )}
      <h3 className="text-lg font-semibold text-gray-800 mb-1">{data.planName || 'Plan'}</h3>
      <div className="mb-3">
        <span className="text-3xl font-bold text-gray-900">{data.price || '$0'}</span>
        {data.period && <span className="text-sm text-gray-500">/{data.period}</span>}
      </div>
      <ul className="space-y-2 mb-4 text-left">
        {features.map((feat, i) => (
          <li key={i} className="flex items-center gap-2 text-sm">
            {feat.included ? (
              <span className="text-green-500 font-bold">&#10003;</span>
            ) : (
              <span className="text-gray-300 font-bold">&#10005;</span>
            )}
            <span className={feat.included ? 'text-gray-700' : 'text-gray-400 line-through'}>{feat.text}</span>
          </li>
        ))}
      </ul>
      <button
        type="button"
        className={`w-full py-2 rounded-md text-sm font-medium cursor-default ${data.highlighted ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 border border-gray-300'}`}
      >
        {data.ctaText || 'Get started'}
      </button>
    </div>
  );
};
