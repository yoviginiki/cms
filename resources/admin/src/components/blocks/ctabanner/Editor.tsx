import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { TextField, ColorField } from '@/components/editor/fields';

export const CtabannerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="divider text-[10px] text-base-content/40 my-1">Content</div>
      <TextField label="Heading" value={(data.heading as string) || ''} onChange={(v) => update('heading', v)} placeholder="Call to action heading" />
      <TextField label="Text" value={(data.text as string) || ''} onChange={(v) => update('text', v)} placeholder="Supporting text" />
      <TextField label="Button Text" value={(data.buttonText as string) || ''} onChange={(v) => update('buttonText', v)} placeholder="Get started" />
      <TextField label="Button URL" value={(data.buttonUrl as string) || ''} onChange={(v) => update('buttonUrl', v)} placeholder="https://..." />

      <div className="divider text-[10px] text-base-content/40 my-1">Background</div>
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <div className="divider text-[10px] text-base-content/40 my-1">Typography</div>
      <ColorField label="Heading Color" value={(data.headingColor as string) || ''} onChange={(v) => update('headingColor', v)} />
      <ColorField label="Text Color" value={(data.textColor as string) || ''} onChange={(v) => update('textColor', v)} />

      <div className="divider text-[10px] text-base-content/40 my-1">Button Styling</div>
      <ColorField label="Button Background" value={(data.btnBgColor as string) || ''} onChange={(v) => update('btnBgColor', v)} />
      <ColorField label="Button Text Color" value={(data.btnTextColor as string) || ''} onChange={(v) => update('btnTextColor', v)} />
      <TextField label="Button Border Radius" value={(data.btnBorderRadius as string) || ''} onChange={(v) => update('btnBorderRadius', v)} placeholder="e.g. 0.5rem, 8px" />
    </div>
  );
};
