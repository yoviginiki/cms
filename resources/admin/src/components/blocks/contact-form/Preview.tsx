import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface FormField {
  label: string;
  type: string;
  required: boolean;
}

export const ContactFormPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    fields: FormField[];
    recipient_email: string;
    success_message: string;
    submit_label: string;
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
            <div className="w-full h-16 bg-gray-50 border border-gray-200 rounded-md" />
          ) : (
            <div className="w-full h-9 bg-gray-50 border border-gray-200 rounded-md" />
          )}
        </div>
      ))}
      <button
        type="button"
        className="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium cursor-default"
      >
        {data.submit_label || 'Send Message'}
      </button>
    </div>
  );
};
