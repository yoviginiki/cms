import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface FormField {
  type: string;
  label: string;
  required: boolean;
  placeholder: string;
}

export const CustomformPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    fields: FormField[];
    submitText: string;
    endpoint: string;
    successMessage: string;
  };

  const fields = data.fields || [];

  return (
    <div className="rounded border border-gray-200 p-4 space-y-3">
      {(fields || []).map((field, index) => (
        <div key={index}>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {field.label}
            {field.required && <span className="text-red-500 ml-1">*</span>}
          </label>
          {field.type === 'textarea' ? (
            <div className="w-full h-16 bg-gray-50 border border-gray-200 rounded-md px-3 py-1 text-xs text-gray-400 flex items-start pt-2">
              {field.placeholder}
            </div>
          ) : field.type === 'checkbox' ? (
            <div className="flex items-center gap-2">
              <div className="w-4 h-4 border border-gray-300 rounded" />
              <span className="text-sm text-gray-500">{field.placeholder || field.label}</span>
            </div>
          ) : field.type === 'select' ? (
            <div className="w-full h-9 bg-gray-50 border border-gray-200 rounded-md px-3 flex items-center text-xs text-gray-400">
              {field.placeholder || 'Select...'}
            </div>
          ) : field.type === 'radio' ? (
            <div className="flex items-center gap-2">
              <div className="w-4 h-4 border border-gray-300 rounded-full" />
              <span className="text-sm text-gray-500">{field.placeholder || field.label}</span>
            </div>
          ) : field.type === 'file' ? (
            <div className="w-full h-9 bg-gray-50 border border-gray-200 border-dashed rounded-md px-3 flex items-center text-xs text-gray-400">
              Choose file...
            </div>
          ) : (
            <div className="w-full h-9 bg-gray-50 border border-gray-200 rounded-md px-3 flex items-center text-xs text-gray-400">
              {field.placeholder}
            </div>
          )}
        </div>
      ))}
      <button
        type="button"
        className="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium cursor-default"
      >
        {data.submitText || 'Send'}
      </button>
    </div>
  );
};
