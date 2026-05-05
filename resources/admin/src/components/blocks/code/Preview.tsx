import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const CodePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    code: string;
    language: string;
    show_line_numbers: boolean;
  };

  const lines = (data.code || '').split('\n');

  return (
    <div className="rounded bg-gray-900 text-gray-100 overflow-hidden">
      <div className="flex items-center justify-between px-4 py-2 bg-gray-800 text-xs text-gray-400">
        <span>{data.language || 'javascript'}</span>
        {data.show_line_numbers && <span>Lines: {lines.length}</span>}
      </div>
      <pre className="p-4 overflow-x-auto text-sm font-mono">
        {data.show_line_numbers ? (
          lines.map((line, i) => (
            <div key={i} className="flex">
              <span className="select-none text-gray-600 pr-4 text-right w-8 inline-block">
                {i + 1}
              </span>
              <span>{line}</span>
            </div>
          ))
        ) : (
          <code>{data.code || '// No code yet'}</code>
        )}
      </pre>
    </div>
  );
};
