import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { TextArea } from '@/components/editor/fields/TextArea';
import { SelectField } from '@/components/editor/fields/SelectField';
import { ToggleField } from '@/components/editor/fields/ToggleField';

interface FormField {
  label: string;
  type: string;
  required: boolean;
}

export const ContactFormEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    fields: FormField[];
    recipient_email: string;
    success_message: string;
    submit_label: string;
  };

  const fields = data.fields || [];

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  const updateField = (index: number, key: keyof FormField, value: string | boolean) => {
    const updated = fields.map((f, i) =>
      i === index ? { ...f, [key]: value } : f,
    );
    update('fields', updated);
  };

  const addField = () => {
    update('fields', [...fields, { label: 'New Field', type: 'text', required: false }]);
  };

  const removeField = (index: number) => {
    if (fields.length <= 1) return;
    update('fields', fields.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-4">
      <TextField
        label="Recipient Email"
        value={data.recipient_email || ''}
        onChange={(v) => update('recipient_email', v)}
        placeholder="admin@example.com"
      />
      <TextField
        label="Submit Button Label"
        value={data.submit_label || ''}
        onChange={(v) => update('submit_label', v)}
      />
      <TextArea
        label="Success Message"
        value={data.success_message || ''}
        onChange={(v) => update('success_message', v)}
        rows={2}
      />

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">Form Fields</label>
        {fields.map((field, index) => (
          <div key={index} className="rounded border border-gray-200 p-3 mb-2 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-gray-500 uppercase">
                Field {index + 1}
              </span>
              <button
                type="button"
                onClick={() => removeField(index)}
                disabled={fields.length <= 1}
                className="text-xs text-red-600 hover:text-red-800 disabled:text-gray-300 disabled:cursor-not-allowed"
              >
                Remove
              </button>
            </div>
            <TextField
              label="Label"
              value={field.label}
              onChange={(v) => updateField(index, 'label', v)}
            />
            <SelectField
              label="Type"
              value={field.type}
              onChange={(v) => updateField(index, 'type', v)}
              options={[
                { value: 'text', label: 'Text' },
                { value: 'email', label: 'Email' },
                { value: 'tel', label: 'Phone' },
                { value: 'textarea', label: 'Textarea' },
                { value: 'select', label: 'Select' },
              ]}
            />
            <ToggleField
              label="Required"
              value={!!field.required}
              onChange={(v) => updateField(index, 'required', v)}
            />
          </div>
        ))}
        <button
          type="button"
          onClick={addField}
          className="text-sm text-blue-600 hover:text-blue-800 font-medium"
        >
          + Add Field
        </button>
      </div>
    </div>
  );
};
