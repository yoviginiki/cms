import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

interface FormField {
  type: string;
  label: string;
  required: boolean;
  placeholder: string;
  options?: string[];
}

export const CustomformEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    fields: FormField[];
    submitText: string;
    endpoint: string;
    successMessage: string;
    formKey?: string;
    notifyEmail?: string;
  };

  const fields = data.fields || [];

  const update = (key: string, value: unknown) => {
    onUpdate({ ...block.data, [key]: value });
  };

  // S5: submissions post to the platform receiver identified by formKey.
  // Auto-generate a stable key the first time the block is edited.
  React.useEffect(() => {
    if (!data.formKey) {
      update('formKey', `form-${Math.random().toString(36).slice(2, 8)}`);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const updateField = (index: number, key: keyof FormField, value: unknown) => {
    const updated = fields.map((f, i) =>
      i === index ? { ...f, [key]: value } : f,
    );
    update('fields', updated);
  };

  const addField = () => {
    update('fields', [...fields, { type: 'text', label: 'New Field', required: false, placeholder: '' }]);
  };

  const removeField = (index: number) => {
    if (fields.length <= 1) return;
    update('fields', fields.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Submit Text</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.submitText || ''}
          onChange={(e) => update('submitText', e.target.value)}
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Form key</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full font-mono"
          value={data.formKey || ''}
          onChange={(e) => update('formKey', e.target.value.toLowerCase().replace(/[^a-z0-9\-_]/g, '-'))}
        />
        <p className="text-[10px] text-base-content/40 mt-1">
          Identifies this form to the platform — submissions are stored per key and viewable under Site Settings → Forms.
        </p>
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Notification email (optional)</label>
        <input
          type="email"
          className="input input-bordered input-sm w-full"
          value={data.notifyEmail || ''}
          onChange={(e) => update('notifyEmail', e.target.value)}
          placeholder="you@example.com"
        />
      </div>
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Success Message</label>
        <input
          type="text"
          className="input input-bordered input-sm w-full"
          value={data.successMessage || ''}
          onChange={(e) => update('successMessage', e.target.value)}
        />
      </div>

      <div>
        <label className="text-[11px] text-base-content/50 mb-2 block">Form Fields</label>
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
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Type</label>
              <select
                className="select select-bordered select-sm w-full"
                value={field.type}
                onChange={(e) => updateField(index, 'type', e.target.value)}
              >
                <option value="text">Text</option>
                <option value="email">Email</option>
                <option value="textarea">Textarea</option>
                <option value="select">Select</option>
                <option value="checkbox">Checkbox</option>
                <option value="radio">Radio</option>
                <option value="file">File</option>
              </select>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Label</label>
              <input
                type="text"
                className="input input-bordered input-sm w-full"
                value={field.label}
                onChange={(e) => updateField(index, 'label', e.target.value)}
              />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Placeholder</label>
              <input
                type="text"
                className="input input-bordered input-sm w-full"
                value={field.placeholder}
                onChange={(e) => updateField(index, 'placeholder', e.target.value)}
              />
            </div>
            {(field.type === 'select' || field.type === 'radio') && (
              <div>
                <label className="text-[11px] text-base-content/50 mb-1 block">Options (one per line)</label>
                <textarea
                  className="textarea textarea-bordered textarea-sm w-full"
                  rows={3}
                  value={(field.options ?? []).join('\n')}
                  onChange={(e) => updateField(index, 'options', e.target.value.split('\n').map((s) => s.trim()).filter(Boolean))}
                />
              </div>
            )}
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                className="checkbox checkbox-sm"
                checked={!!field.required}
                onChange={(e) => updateField(index, 'required', e.target.checked)}
              />
              <label className="text-[11px] text-base-content/50">Required</label>
            </div>
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
