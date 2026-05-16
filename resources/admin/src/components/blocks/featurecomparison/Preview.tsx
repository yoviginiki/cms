import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface Plan {
  name: string;
  price: string;
}

interface Feature {
  name: string;
  values: (boolean | string)[];
}

export const FeaturecomparisonPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    plans: Plan[];
    features: Feature[];
  };

  const plans = data.plans || [];
  const features = data.features || [];

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm border-collapse">
        <thead>
          <tr>
            <th className="text-left p-2 border-b border-gray-200 text-gray-500 font-medium">Feature</th>
            {(plans || []).map((plan, i) => (
              <th key={i} className="text-center p-2 border-b border-gray-200">
                <div className="font-semibold text-gray-800">{plan.name}</div>
                <div className="text-xs text-gray-500">{plan.price}</div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {(features || []).map((feat, fi) => (
            <tr key={fi} className={fi % 2 === 0 ? 'bg-gray-50' : ''}>
              <td className="p-2 text-gray-700">{feat.name}</td>
              {(feat.values || []).map((val, vi) => (
                <td key={vi} className="text-center p-2">
                  {typeof val === 'boolean' ? (
                    val ? (
                      <span className="text-green-500 font-bold">&#10003;</span>
                    ) : (
                      <span className="text-gray-300 font-bold">&#10005;</span>
                    )
                  ) : (
                    <span className="text-gray-700">{val}</span>
                  )}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
