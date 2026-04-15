import { X } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';
import { NumberField } from '@/components/editor/fields/NumberField';
import type { BlockData } from '@/types/blocks';

function findBlock(blocks: BlockData[], id: string): BlockData | null {
  for (const block of blocks) {
    if (block.id === id) return block;
    const found = findBlock(block.children, id);
    if (found) return found;
  }
  return null;
}

export function BlockSettings() {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const blocks = useEditorStore((s) => s.blocks);
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);

  if (!selectedBlockId) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-gray-400">
        Select a block to edit
      </div>
    );
  }

  const block = findBlock(blocks, selectedBlockId);
  if (!block) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-gray-400">
        Block not found
      </div>
    );
  }

  const registration = blockRegistry.get(block.type);
  if (!registration) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-gray-400">
        Unknown block type
      </div>
    );
  }

  const { Editor } = registration;

  const handleUpdate = (data: Record<string, unknown>) => {
    updateBlock(selectedBlockId, data);
  };

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between p-3 border-b border-gray-200">
        <h3 className="text-sm font-semibold text-gray-800">
          {registration.definition.label}
        </h3>
        <button
          onClick={() => selectBlock(null)}
          className="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="flex-1 overflow-y-auto p-3">
        <Editor
          block={block}
          isSelected={true}
          onUpdate={handleUpdate}
          onSelect={() => {}}
        />

        <div className="mt-6 pt-4 border-t border-gray-200">
          <h4 className="text-xs font-semibold uppercase text-gray-500 mb-3">
            Common Settings
          </h4>

          <TextField
            label="CSS Class"
            value={(block.data.cssClass as string) ?? ''}
            onChange={(val) => handleUpdate({ cssClass: val })}
            placeholder="e.g. my-custom-class"
          />

          <SelectField
            label="Visibility"
            value={(block.data.visibility as string) ?? 'visible'}
            onChange={(val) => handleUpdate({ visibility: val })}
            options={[
              { value: 'visible', label: 'Visible' },
              { value: 'hidden', label: 'Hidden' },
            ]}
          />

          <NumberField
            label="Margin Top (px)"
            value={(block.data.marginTop as number) ?? 0}
            onChange={(val) => handleUpdate({ marginTop: val })}
            min={0}
          />

          <NumberField
            label="Margin Bottom (px)"
            value={(block.data.marginBottom as number) ?? 0}
            onChange={(val) => handleUpdate({ marginBottom: val })}
            min={0}
          />

          <NumberField
            label="Padding Top (px)"
            value={(block.data.paddingTop as number) ?? 0}
            onChange={(val) => handleUpdate({ paddingTop: val })}
            min={0}
          />

          <NumberField
            label="Padding Bottom (px)"
            value={(block.data.paddingBottom as number) ?? 0}
            onChange={(val) => handleUpdate({ paddingBottom: val })}
            min={0}
          />
        </div>
      </div>
    </div>
  );
}
