import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const TabsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { tab_labels: string[] };
  const labels = data.tab_labels || ['Tab 1', 'Tab 2'];

  const updateLabel = (index: number, value: string) => {
    const updated = [...labels];
    updated[index] = value;
    onUpdate({ ...block.data, tab_labels: updated });
  };

  const addTab = () => {
    onUpdate({ ...block.data, tab_labels: [...labels, `Tab ${labels.length + 1}`] });
  };

  const removeTab = (index: number) => {
    if (labels.length <= 1) return;
    const updated = labels.filter((_, i) => i !== index);
    onUpdate({ ...block.data, tab_labels: updated });
  };

  return (
    <div className="space-y-3">
      <label className="block text-sm font-medium text-gray-700">Tab Labels</label>
      {labels.map((label, index) => (
        <div key={index} className="flex items-center gap-2">
          <input
            type="text"
            value={label}
            onChange={(e) => updateLabel(index, e.target.value)}
            className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          />
          <button
            type="button"
            onClick={() => removeTab(index)}
            disabled={labels.length <= 1}
            className="px-2 py-2 text-sm text-red-600 hover:text-red-800 disabled:text-gray-300 disabled:cursor-not-allowed"
          >
            Remove
          </button>
        </div>
      ))}
      <button
        type="button"
        onClick={addTab}
        className="text-sm text-blue-600 hover:text-blue-800 font-medium"
      >
        + Add Tab
      </button>
    </div>
  );
};
