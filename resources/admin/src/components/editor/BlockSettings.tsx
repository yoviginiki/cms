import { useState } from 'react';
import { X, ChevronDown, ChevronRight } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import type { BlockData, BlockStyleProps } from '@/types/blocks';
import { TypographyPanel } from './properties/TypographyPanel';
import { SpacingPanel } from './properties/SpacingPanel';
import { VisualPanel } from './properties/VisualPanel';
import { LayoutPanel } from './properties/LayoutPanel';
import { AnimationPanel } from './properties/AnimationPanel';
import { AdvancedPanel } from './properties/AdvancedPanel';
import { ResponsivePanel } from './properties/ResponsivePanel';

function findBlock(blocks: BlockData[], id: string): BlockData | null {
  for (const block of blocks) {
    if (block.id === id) return block;
    const found = findBlock(block.children, id);
    if (found) return found;
  }
  return null;
}

function Section({ title, children, defaultOpen = false }: { title: string; children: React.ReactNode; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border-t border-base-300/20">
      <button onClick={() => setOpen(!open)}
        className="w-full flex items-center gap-1.5 py-2 text-[10px] font-medium text-base-content/40 uppercase tracking-wider hover:text-base-content/60">
        {open ? <ChevronDown size={11} /> : <ChevronRight size={11} />}
        {title}
      </button>
      {open && <div className="pb-3">{children}</div>}
    </div>
  );
}

export function BlockSettings() {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const blocks = useEditorStore((s) => s.blocks);
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);

  if (!selectedBlockId) {
    return (
      <div className="flex items-center justify-center h-full text-[12px] text-base-content/30">
        Select a block to edit
      </div>
    );
  }

  const block = findBlock(blocks, selectedBlockId);
  if (!block) {
    return (
      <div className="flex items-center justify-center h-full text-[12px] text-base-content/30">
        Block not found
      </div>
    );
  }

  const registration = blockRegistry.get(block.type);
  if (!registration) {
    return (
      <div className="flex items-center justify-center h-full text-[12px] text-base-content/30">
        Unknown block type
      </div>
    );
  }

  const { Editor } = registration;
  const style: BlockStyleProps = block.style || {};

  const handleUpdate = (data: Record<string, unknown>) => {
    updateBlock(selectedBlockId, data);
  };

  const updateStyle = (section: keyof BlockStyleProps, value: unknown) => {
    // Store style in block.data.__style for now (until store supports style field)
    handleUpdate({ __style: { ...style, [section]: value } });
  };

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between p-3 border-b border-base-300/20">
        <h3 className="text-[12px] font-medium text-base-content/80">
          {registration.definition.label}
        </h3>
        <button onClick={() => selectBlock(null)}
          className="btn btn-ghost btn-xs btn-square text-base-content/30 hover:text-base-content/60">
          <X className="h-3.5 w-3.5" />
        </button>
      </div>

      <div className="flex-1 overflow-y-auto p-3 space-y-1">
        {/* Block-specific settings */}
        <Section title="Content" defaultOpen={true}>
          <Editor block={block} isSelected={true} onUpdate={handleUpdate} onSelect={() => {}} />
        </Section>

        {/* Typography — for text-containing blocks */}
        {registration.definition.hasTypography !== false && (
          <Section title="Typography">
            <TypographyPanel value={style.typography || {}} onChange={v => updateStyle('typography', v)} />
          </Section>
        )}

        {/* Spacing */}
        <Section title="Spacing">
          <SpacingPanel value={style.spacing || {}} onChange={v => updateStyle('spacing', v)} />
        </Section>

        {/* Visual */}
        <Section title="Background & borders">
          <VisualPanel value={style.visual || {}} onChange={v => updateStyle('visual', v)} />
        </Section>

        {/* Layout */}
        <Section title="Layout">
          <LayoutPanel value={style.layout || {}} onChange={v => updateStyle('layout', v)} />
        </Section>

        {/* Animation */}
        <Section title="Animation">
          <AnimationPanel value={block.animation || {}} onChange={v => handleUpdate({ __animation: v })} />
        </Section>

        {/* Responsive */}
        <Section title="Responsive">
          <ResponsivePanel value={block.responsive || {}} onChange={v => handleUpdate({ __responsive: v })} />
        </Section>

        {/* Advanced */}
        <Section title="Advanced">
          <AdvancedPanel value={block.advanced || {}} onChange={v => handleUpdate({ __advanced: v })} />
        </Section>
      </div>
    </div>
  );
}
