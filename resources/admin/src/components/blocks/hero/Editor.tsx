import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const HeroEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { title, subtitle, ctaText, ctaUrl } = block.data as {
    title: string;
    subtitle: string;
    ctaText: string;
    ctaUrl: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
        <input
          type="text"
          value={title || ''}
          onChange={(e) => update('title', e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Subtitle</label>
        <input
          type="text"
          value={subtitle || ''}
          onChange={(e) => update('subtitle', e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">CTA Text</label>
        <input
          type="text"
          value={ctaText || ''}
          onChange={(e) => update('ctaText', e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">CTA URL</label>
        <input
          type="text"
          value={ctaUrl || ''}
          onChange={(e) => update('ctaUrl', e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
    </div>
  );
};
