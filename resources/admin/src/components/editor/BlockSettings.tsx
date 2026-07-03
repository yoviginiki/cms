import { useState } from 'react';
import { X, ChevronDown, ChevronRight, MousePointerClick } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import { BlockIcon } from './BlockIcon';
import type { BlockData, BlockStyleProps } from '@/types/blocks';
import { SpacingPanel } from './properties/SpacingPanel';
import { VisualPanel } from './properties/VisualPanel';
import { LayoutPanel } from './properties/LayoutPanel';
import { AnimationPanel } from './properties/AnimationPanel';
import { AdvancedPanel } from './properties/AdvancedPanel';
import { ResponsivePanel } from './properties/ResponsivePanel';
import { TypographyPanel } from './properties/TypographyPanel';
import { LayerTransformPanel } from './properties/LayerTransformPanel';
import { AnimationScenePanel } from './properties/AnimationScenePanel';
import BackgroundEditor from './BackgroundEditor';

function findBlock(blocks: BlockData[], id: string): BlockData | null {
  for (const block of blocks) {
    if (block.id === id) return block;
    const found = findBlock(block.children, id);
    if (found) return found;
  }
  return null;
}

function findParent(blocks: BlockData[], id: string, parent: BlockData | null = null): BlockData | null {
  for (const block of blocks) {
    if (block.id === id) return parent;
    const found = findParent(block.children, id, block);
    if (found !== null) return found;
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
      <div className="flex flex-col items-center justify-center h-full text-center px-6 gap-2">
        <MousePointerClick size={28} className="text-base-content/15" />
        <p className="text-[12px] text-base-content/40 font-medium">Select a section to edit its settings</p>
        <p className="text-[10px] text-base-content/25">Click any block on the canvas to see its controls here</p>
      </div>
    );
  }

  const block = findBlock(blocks, selectedBlockId);
  const parentBlock = findParent(blocks, selectedBlockId);
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
        <div className="flex items-center gap-2">
          <BlockIcon icon={registration.definition.icon ?? 'Box'} size={14} className="text-primary/60" />
          <div>
            <h3 className="text-[12px] font-medium text-base-content/80">{registration.definition.label}</h3>
            {registration.definition.description && (
              <p className="text-[9px] text-base-content/30">{registration.definition.description}</p>
            )}
          </div>
        </div>
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

        {/* Layer transform: only for absolutely-positioned layers (blocks
            inside a slide, or anything already carrying data.layout) */}
        {(parentBlock?.type === 'slide' || (block.data as Record<string, unknown>)?.layout != null) && (
          <Section title="Transform" defaultOpen={true}>
            <LayerTransformPanel
              value={((block.data as Record<string, unknown>)?.layout as Record<string, unknown>) || {}}
              onChange={layout => handleUpdate({ layout })}
              responsive={((block.data as Record<string, unknown>)?.responsiveLayout as Record<string, unknown>) || {}}
              onResponsiveChange={responsiveLayout => handleUpdate({ responsiveLayout })}
            />
          </Section>
        )}

        {/* Scene animation (IN/LOOP/OUT): layers inside a slide only */}
        {parentBlock?.type === 'slide' && (
          <Section title="Animation" defaultOpen={false}>
            <AnimationScenePanel
              value={((block.data as Record<string, unknown>)?.animation as Record<string, unknown>) || {}}
              onChange={animation => handleUpdate({ animation })}
            />
          </Section>
        )}

        {/* Typography is handled per-block (Heading, Hero have their own controls).
            The shared TypographyPanel was removed because it stored data but never
            applied it — blocks that need typography implement it directly. */}

        {/* Typography */}
        <Section title="Typography" defaultOpen={true}>
          <TypographyPanel
            value={style.typography || {}}
            onChange={v => updateStyle('typography', v)}
            style={style}
            responsive={block.responsive}
            onResponsiveChange={v => handleUpdate({ __responsive: v })}
          />
        </Section>

        {/* Spacing */}
        <Section title="Spacing">
          <SpacingPanel
            value={style.spacing || {}}
            onChange={v => updateStyle('spacing', v)}
            style={style}
            responsive={block.responsive}
            onResponsiveChange={v => handleUpdate({ __responsive: v })}
          />
        </Section>

        {/* Background (full Divi-quality: color picker, gradient builder, image + overlay) */}
        <Section title="Background">
          <BackgroundEditor data={block.data || {}} onChange={handleUpdate} />
        </Section>

        {/* Borders & Shadow */}
        <Section title="Borders & Shadow">
          <VisualPanel value={style.visual || {}} onChange={v => updateStyle('visual', v)} hideBg />
        </Section>

        {/* Size & Layout */}
        <Section title="Size & Layout">
          <LayoutPanel
            value={style.layout || {}}
            onChange={v => updateStyle('layout', v)}
            style={style}
            responsive={block.responsive}
            onResponsiveChange={v => handleUpdate({ __responsive: v })}
          />
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
