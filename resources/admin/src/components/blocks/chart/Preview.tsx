import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface ChartDataItem {
  label: string;
  value: number;
}

export const ChartPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { chartType: string; data: ChartDataItem[]; title: string; showLegend: boolean };
  const items = data.data || [];
  const maxVal = Math.max(...(items || []).map((d) => d.value), 1);
  const colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];

  return (
    <div className="p-4 rounded-lg border border-gray-200">
      {data.title && <div className="text-sm font-semibold mb-3">{data.title}</div>}
      {(data.chartType === 'bar' || data.chartType === 'line') && (
        <div className="space-y-2">
          {(items || []).map((item, i) => (
            <div key={i} className="flex items-center gap-2">
              <span className="text-xs w-12 text-right text-gray-500">{item.label}</span>
              <div className="flex-1 bg-gray-100 rounded h-5 overflow-hidden">
                <div
                  className="h-full rounded"
                  style={{ width: `${(item.value / maxVal) * 100}%`, backgroundColor: colors[i % colors.length] }}
                />
              </div>
              <span className="text-xs w-8 text-gray-500">{item.value}</span>
            </div>
          ))}
        </div>
      )}
      {(data.chartType === 'pie' || data.chartType === 'donut') && (
        <div className="flex items-center gap-4">
          <div className="w-20 h-20 rounded-full bg-gradient-to-tr from-blue-500 to-green-400 flex items-center justify-center">
            {data.chartType === 'donut' && <div className="w-10 h-10 rounded-full bg-white" />}
          </div>
          {data.showLegend && (
            <div className="space-y-1">
              {(items || []).map((item, i) => (
                <div key={i} className="flex items-center gap-1">
                  <div className="w-2 h-2 rounded-full" style={{ backgroundColor: colors[i % colors.length] }} />
                  <span className="text-xs text-gray-600">{item.label}: {item.value}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};
