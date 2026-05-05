import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';
import { ToggleField } from '@/components/editor/fields/ToggleField';

export const CodeEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    code: string;
    language: string;
    show_line_numbers: boolean;
  };

  const update = (field: string, value: string | boolean) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Code</label>
        <textarea
          value={data.code || ''}
          onChange={(e) => update('code', e.target.value)}
          rows={10}
          className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500"
          placeholder="// Your code here..."
        />
      </div>
      <SelectField
        label="Language"
        value={data.language || 'javascript'}
        onChange={(v) => update('language', v)}
        options={[
          { value: 'javascript', label: 'JavaScript' },
          { value: 'typescript', label: 'TypeScript' },
          { value: 'python', label: 'Python' },
          { value: 'php', label: 'PHP' },
          { value: 'html', label: 'HTML' },
          { value: 'css', label: 'CSS' },
          { value: 'json', label: 'JSON' },
          { value: 'bash', label: 'Bash' },
          { value: 'sql', label: 'SQL' },
        ]}
      />
      <ToggleField
        label="Show Line Numbers"
        value={!!data.show_line_numbers}
        onChange={(v) => update('show_line_numbers', v)}
      />
    </div>
  );
};
