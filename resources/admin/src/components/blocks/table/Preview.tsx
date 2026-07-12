import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const TablePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    headers: string[];
    rows: string[][];
    striped: boolean;
    compact: boolean;
    caption?: string;
  };

  const headers = data.headers || [];
  const rows = data.rows || [];
  const pad = data.compact ? 'px-2 py-1 text-xs' : 'px-3 py-2 text-sm';

  return (
    <div className="overflow-x-auto rounded border border-gray-200">
      <table className="w-full border-collapse">
        {data.caption ? <caption className={`${pad} text-left text-gray-500`} style={{ captionSide: 'top' }}>{data.caption}</caption> : null}
        <thead>
          <tr className="bg-gray-100">
            {(headers || []).map((h, i) => (
              <th key={i} className={`${pad} text-left font-semibold border-b border-gray-200`}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {(rows || []).map((row, ri) => (
            <tr key={ri} className={data.striped && ri % 2 === 1 ? 'bg-gray-50' : ''}>
              {(row || []).map((cell, ci) => (
                <td key={ci} className={`${pad} border-b border-gray-100`}>{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
