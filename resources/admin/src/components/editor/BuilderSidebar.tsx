import { useState, useEffect } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { BlockPicker } from '@/components/editor/BlockPicker';
import { BlockSettings } from '@/components/editor/BlockSettings';

type Tab = 'blocks' | 'settings';

export function BuilderSidebar() {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const [activeTab, setActiveTab] = useState<Tab>('blocks');

  useEffect(() => {
    if (selectedBlockId) {
      setActiveTab('settings');
    } else {
      setActiveTab('blocks');
    }
  }, [selectedBlockId]);

  return (
    <div className="w-80 border-l border-gray-200 bg-white h-full overflow-y-auto flex flex-col">
      <div className="flex border-b border-gray-200">
        <button
          onClick={() => setActiveTab('blocks')}
          className={`flex-1 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
            activeTab === 'blocks'
              ? 'border-blue-500 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          Blocks
        </button>
        <button
          onClick={() => setActiveTab('settings')}
          className={`flex-1 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
            activeTab === 'settings'
              ? 'border-blue-500 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          Settings
        </button>
      </div>

      <div className="flex-1 overflow-y-auto">
        {activeTab === 'blocks' ? <BlockPicker /> : <BlockSettings />}
      </div>
    </div>
  );
}
