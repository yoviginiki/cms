import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const ListPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { items, listType } = block.data as {
    items: string[];
    listType: string;
  };

  const listItems = items || [];

  if (listItems.length === 0) {
    return (
      <div className="text-base-content/40 italic py-2">
        Click to add list items...
      </div>
    );
  }

  if (listType === 'numbered') {
    return (
      <ol className="list-decimal list-inside text-base-content/80 space-y-1">
        {(listItems || []).map((item, i) => (
          <li key={i}>{item}</li>
        ))}
      </ol>
    );
  }

  if (listType === 'checklist') {
    return (
      <ul className="space-y-1">
        {(listItems || []).map((item, i) => (
          <li key={i} className="flex items-center gap-2 text-base-content/80">
            <input type="checkbox" className="checkbox checkbox-sm" disabled />
            <span>{item}</span>
          </li>
        ))}
      </ul>
    );
  }

  return (
    <ul className="list-disc list-inside text-base-content/80 space-y-1">
      {(listItems || []).map((item, i) => (
        <li key={i}>{item}</li>
      ))}
    </ul>
  );
};
